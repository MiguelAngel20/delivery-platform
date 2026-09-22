<?php

namespace App\Notifications\Auth;

use App\Models\User;
use App\Support\Portal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class DriverEmailVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var User $notifiable */
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Verifica tu correo de repartidor ChisDrive')
            ->greeting('Hola '.$notifiable->first_name)
            ->line('Te registramos como repartidor en ChisDrive. Para activar tu acceso, verifica tu correo electrónico.')
            ->action('Verificar correo', $verificationUrl)
            ->line('Este enlace caduca en 48 horas.')
            ->line('Si no esperabas este mensaje, puedes ignorarlo.');
    }

    private function verificationUrl(User $user): string
    {
        $previousRoot = config('app.url');

        if (Portal::enabled()) {
            URL::forceRootUrl(Portal::baseUrl(Portal::DRIVER));
        }

        try {
            return URL::temporarySignedRoute(
                'driver.verification.verify',
                now()->addHours(48),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1((string) $user->email),
                ],
            );
        } finally {
            URL::forceRootUrl($previousRoot);
        }
    }
}
