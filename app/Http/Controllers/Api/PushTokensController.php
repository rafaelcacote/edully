<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokensController extends Controller
{
    /**
     * Register or refresh the device Expo push token for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:255'],
            'platform' => ['nullable', 'string', 'in:ios,android,web'],
            'tenant_id' => ['nullable', 'uuid'],
        ]);

        $token = PushToken::query()->updateOrCreate(
            ['push_token' => $validated['push_token']],
            [
                'usuario_id' => $request->user()->id,
                'platform' => $validated['platform'] ?? null,
                'tenant_id' => $validated['tenant_id'] ?? null,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Push token registrado com sucesso.',
            'push_token' => [
                'id' => $token->id,
                'platform' => $token->platform,
                'updated_at' => $token->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Remove a device push token (logout / uninstall).
     */
    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'push_token' => ['required', 'string', 'max:255'],
        ]);

        PushToken::query()
            ->where('usuario_id', $request->user()->id)
            ->where('push_token', $validated['push_token'])
            ->delete();

        return response()->json([
            'message' => 'Push token removido com sucesso.',
        ]);
    }
}
