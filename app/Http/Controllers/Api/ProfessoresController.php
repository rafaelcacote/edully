<?php

namespace App\Http\Controllers\Api;

use App\Actions\Api\ListStudentTeachersAction;
use App\Actions\Api\ResolveLinkedStudentAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\TeacherResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessoresController extends Controller
{
    /**
     * List teachers linked to the student's active classes for the authenticated responsavel.
     */
    public function index(
        Request $request,
        string $id,
        ResolveLinkedStudentAction $resolveLinkedStudent,
        ListStudentTeachersAction $listStudentTeachers,
    ): JsonResponse {
        $aluno = $resolveLinkedStudent->execute($request->user(), $id);
        $professores = $listStudentTeachers->execute($aluno);

        return response()->json([
            'professores' => TeacherResource::collection($professores),
        ]);
    }
}
