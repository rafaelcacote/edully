<?php

namespace App\Actions\Fortify;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetResponse implements PasswordResetResponseContract
{
    public function __construct(protected string $status) {}

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($this->status)], 200);
        }

        // Pais/responsáveis acessam só pelo app — não redirecionar para o login web.
        return redirect()
            ->route('password.reset.success')
            ->with('status', trans($this->status));
    }
}
