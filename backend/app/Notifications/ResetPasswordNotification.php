<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] public string $token,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');
        $resetUrl = $frontendUrl.'/reset-password?token='.urlencode($this->token)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu — BookStore AI')
            ->greeting('Xin chào '.$notifiable->name.'!')
            ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản BookStore AI của bạn.')
            ->action('Đặt lại mật khẩu', $resetUrl)
            ->line('Liên kết này sẽ hết hạn sau '.config('auth.passwords.users.expire').' phút.')
            ->line('Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.');
    }
}
