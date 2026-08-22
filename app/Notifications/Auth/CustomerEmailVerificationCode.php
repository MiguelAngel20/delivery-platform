<?php

namespace App\Notifications\Auth;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerEmailVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code)
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
        $minutes = (int) config('business.customers.email_verification_ttl_minutes', 15);

        return (new MailMessage)
            ->subject('Tu código de verificación RIDE')
            ->greeting('Hola '.$notifiable->first_name)
            ->line('Usa este código para verificar tu cuenta y continuar con tu pedido:')
            ->line('**'.$this->code.'**')
            ->line("El código caduca en {$minutes} minutos.")
            ->line('Si no creaste esta cuenta, puedes ignorar este correo.');
    }
}
