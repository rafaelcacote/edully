<?php

namespace App\Services;

use App\Models\PushToken;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param  list<string>  $usuarioIds
     * @param  array<string, mixed>  $data
     */
    public function sendToUsers(array $usuarioIds, string $title, string $body, array $data = []): void
    {
        $usuarioIds = array_values(array_unique(array_filter($usuarioIds)));
        if ($usuarioIds === []) {
            return;
        }

        $tokens = PushToken::query()
            ->whereIn('usuario_id', $usuarioIds)
            ->pluck('push_token')
            ->filter()
            ->unique()
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * @param  Collection<int, string>  $tokens
     * @param  array<string, mixed>  $data
     */
    public function sendToTokens(Collection $tokens, string $title, string $body, array $data = []): void
    {
        $messages = $tokens->map(fn (string $token) => [
            'to' => $token,
            'sound' => 'default',
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channelId' => 'eduly-alertas',
            'priority' => 'high',
        ])->values()->all();

        foreach (array_chunk($messages, 100) as $chunk) {
            try {
                $response = Http::acceptJson()
                    ->asJson()
                    ->timeout(10)
                    ->post(self::EXPO_PUSH_URL, $chunk);

                if (! $response->successful()) {
                    Log::warning('Expo push request failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);

                    continue;
                }

                $this->pruneInvalidTokens($response->json('data') ?? [], $chunk);
            } catch (\Throwable $e) {
                Log::warning('Expo push exception: '.$e->getMessage());
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $tickets
     * @param  list<array<string, mixed>>  $chunk
     */
    protected function pruneInvalidTokens(array $tickets, array $chunk): void
    {
        foreach ($tickets as $index => $ticket) {
            $status = $ticket['status'] ?? null;
            if ($status !== 'error') {
                continue;
            }

            $error = $ticket['details']['error'] ?? ($ticket['message'] ?? '');
            if (! in_array($error, ['DeviceNotRegistered', 'InvalidCredentials'], true)
                && ! str_contains(strtolower((string) $error), 'not registered')) {
                continue;
            }

            $token = $chunk[$index]['to'] ?? null;
            if (! is_string($token) || $token === '') {
                continue;
            }

            PushToken::query()->where('push_token', $token)->delete();
        }
    }
}
