<?php

namespace App\Http\Controllers\Api;

use App\Enums\CategoriaDeclaracao;
use App\Enums\StatusDocumento;
use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDocumentoRequest;
use App\Http\Requests\Api\UploadDocumentoAnexoRequest;
use App\Http\Resources\Api\DocumentoResource;
use App\Models\Documento;
use App\Models\Responsavel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentosController extends Controller
{
    /**
     * List documents for a student owned by the authenticated responsavel.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem acessar os documentos.',
            ], 403);
        }

        $alunoId = $request->query('aluno_id');
        if (! is_string($alunoId) || $alunoId === '') {
            return response()->json([
                'message' => 'Informe o aluno_id.',
            ], 422);
        }

        $student = $this->findOwnedStudent($user, $alunoId);
        if (! $student) {
            return response()->json([
                'message' => 'Aluno não encontrado ou você não tem permissão para acessá-lo.',
            ], 403);
        }

        $documentos = Documento::query()
            ->where('aluno_id', $student->id)
            ->where('tenant_id', $student->tenant_id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'documentos' => DocumentoResource::collection($documentos),
        ]);
    }

    /**
     * Show a single document owned by the authenticated responsavel.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem acessar os documentos.',
            ], 403);
        }

        $documento = Documento::query()->find($id);
        if (! $documento || ! $this->findOwnedStudent($user, $documento->aluno_id)) {
            return response()->json([
                'message' => 'Documento não encontrado.',
            ], 404);
        }

        return response()->json([
            'documento' => new DocumentoResource($documento),
        ]);
    }

    /**
     * Create an atestado or pedido_declaracao from the responsavel app.
     */
    public function store(StoreDocumentoRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem criar documentos.',
            ], 403);
        }

        $validated = $request->validated();
        $student = $this->findOwnedStudent($user, $validated['aluno_id']);

        if (! $student) {
            return response()->json([
                'message' => 'Aluno não encontrado ou você não tem permissão para acessá-lo.',
            ], 403);
        }

        $tipo = TipoDocumento::from($validated['tipo']);
        $titulo = filled($validated['titulo'] ?? null)
            ? $validated['titulo']
            : $this->defaultTitulo($tipo, $validated['categoria_declaracao'] ?? null);

        $anexoUrl = null;
        if ($request->hasFile('anexo')) {
            $anexoUrl = $this->storeAnexo($request->file('anexo'));
        }

        $documento = Documento::create([
            'tenant_id' => $student->tenant_id,
            'aluno_id' => $student->id,
            'criado_por' => $user->id,
            'tipo' => $tipo,
            'status' => StatusDocumento::Enviado,
            'titulo' => $titulo,
            'descricao' => $validated['descricao'] ?? null,
            'data_inicio' => $validated['data_inicio'] ?? null,
            'data_fim' => $validated['data_fim'] ?? null,
            'categoria_declaracao' => $validated['categoria_declaracao'] ?? null,
            'anexo_url' => $anexoUrl,
        ]);

        return response()->json([
            'documento' => new DocumentoResource($documento),
        ], 201);
    }

    /**
     * Upload or replace the anexo of a document still owned by the responsavel.
     */
    public function uploadAnexo(UploadDocumentoAnexoRequest $request, string $id): JsonResponse
    {
        $user = $request->user();

        if (! $user->isResponsavel()) {
            return response()->json([
                'message' => 'Acesso negado. Apenas responsáveis podem enviar anexos.',
            ], 403);
        }

        $documento = Documento::query()->find($id);
        if (! $documento || ! $this->findOwnedStudent($user, $documento->aluno_id)) {
            return response()->json([
                'message' => 'Documento não encontrado.',
            ], 404);
        }

        if ($documento->tipo === TipoDocumento::DocumentoEscola) {
            return response()->json([
                'message' => 'Não é possível anexar arquivo a um documento enviado pela escola.',
            ], 422);
        }

        if (in_array($documento->status, [
            StatusDocumento::Aprovado,
            StatusDocumento::Recusado,
            StatusDocumento::Atendido,
            StatusDocumento::Cancelado,
        ], true)) {
            return response()->json([
                'message' => 'Não é possível alterar o anexo de um documento já finalizado.',
            ], 422);
        }

        $this->deleteStoredAnexo($documento->anexo_url);
        $documento->anexo_url = $this->storeAnexo($request->file('anexo'));
        $documento->save();

        return response()->json([
            'documento' => new DocumentoResource($documento->fresh()),
        ]);
    }

    protected function defaultTitulo(TipoDocumento $tipo, ?string $categoria): string
    {
        if ($tipo === TipoDocumento::PedidoDeclaracao && filled($categoria)) {
            return CategoriaDeclaracao::tryFrom($categoria)?->label()
                ?? 'Pedido de declaração';
        }

        return $tipo->label();
    }

    protected function storeAnexo(UploadedFile $file): string
    {
        $path = $file->store('documentos/anexos', 'public');

        return asset('storage/'.$path);
    }

    protected function deleteStoredAnexo(?string $url): void
    {
        if (! $url) {
            return;
        }

        $storageBaseUrl = asset('storage/');
        if (! str_starts_with($url, $storageBaseUrl)) {
            return;
        }

        $path = str_replace($storageBaseUrl, '', $url);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Resolve a student that belongs to the authenticated responsavel.
     */
    protected function findOwnedStudent(User $user, string $alunoId): ?Student
    {
        $responsavelIds = Responsavel::query()
            ->where('usuario_id', $user->id)
            ->pluck('id')
            ->all();

        if ($responsavelIds === []) {
            return null;
        }

        $driver = DB::connection('shared')->getDriverName();
        $pivotTable = $driver === 'sqlite' ? 'aluno_responsavel' : 'escola.aluno_responsavel';

        $linked = DB::connection('shared')
            ->table($pivotTable)
            ->whereIn('responsavel_id', $responsavelIds)
            ->where('aluno_id', $alunoId)
            ->exists();

        if (! $linked) {
            return null;
        }

        return Student::query()
            ->where('id', $alunoId)
            ->where('ativo', true)
            ->first();
    }
}
