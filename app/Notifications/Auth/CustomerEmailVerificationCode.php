<?php

namespace App\Notifications\Auth;

use App\Services\Notifications\NotificationIdempotencyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * One intentional OTP send = one issuanceId.
 *
 * Explicit resend mints a new issuanceId (allowed). Queue retries / duplicate
 * jobs for the same issuanceId must not send another email.
 * Claim failure/retry is handled via NotificationFailed / NotificationSent listeners.
 */
class CustomerEmailVerificationCode extends Notification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public string $code,
        public string $issuanceId,
    ) {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return $this->issuanceId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $idempotency = app(NotificationIdempotencyService::class);

        if (! $idempotency->claim($idempotency->otpMailKey($this->issuanceId))) {
            return [];
        }

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
