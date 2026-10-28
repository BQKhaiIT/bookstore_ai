<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('Xác minh email — BookStore AI')
            ->greeting('Xin chào '.$notifiable->name.'!')
            ->line('Cảm ơn bạn đã đăng ký tài khoản tại BookStore AI.')
            ->line('Vui lòng nhấn nút bên dưới để xác minh địa chỉ email của bạn.')
            ->action('Xác minh email', $verificationUrl)
            ->line('Liên kết này sẽ hết hạn sau '.Config::get('auth.verification.expire', 60).' phút.')
            ->line('Nếu bạn không tạo tài khoản, bạn có thể bỏ qua email này.');
    }

    protected function verificationUrl($notifiable): string
    {
        return URL::temporarySignedRoute(
            'verification.verify',
            Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
