<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    /**
     * Build the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        $url = $this->resetUrl($notifiable);

        $mail = (new MailMessage)
            ->subject('Redefinir senha — '.config('app.name'))
            ->greeting('Olá!')
            ->line('Você solicitou a redefinição de senha da sua conta no Edully.')
            ->action('Redefinir senha', $url)
            ->line('Este link expira em '.config('auth.passwords.users.expire', 60).' minutos.')
            ->line('Se você não solicitou a redefinição, ignore este e-mail.')
            ->salutation('Atenciosamente, Equipe Edully');

        return $mail;
    }

    /**
     * URL do botão do e-mail: sempre HTTPS/HTTP (clientes de e-mail bloqueiam deep links).
     *
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        $email = $notifiable->getEmailForPasswordReset();

        // 1) URL web pública do app (Expo web / site), se configurada
        $frontendResetUrl = config('app.frontend_reset_url');
        if (is_string($frontendResetUrl) && $frontendResetUrl !== '') {
            $separator = str_contains($frontendResetUrl, '?') ? '&' : '?';

            return $frontendResetUrl.$separator.http_build_query([
                'token' => $this->token,
                'email' => $email,
            ]);
        }

        // 2) Tela web do Laravel (Fortify) — funciona em Gmail, Outlook, etc.
        return url(route('password.reset', [
            'token' => $this->token,
            'email' => $email,
        ], false));
    }
}
