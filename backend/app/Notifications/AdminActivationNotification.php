<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminActivationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private int $invitationId,
        private string $plainToken,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');
        $email = strtolower((string) ($notifiable->routeNotificationFor('mail') ?? $notifiable->email ?? ''));
        $url = $frontend.'/admin/activate?invitation='.$this->invitationId
            .'&email='.rawurlencode($email)
            .'&token='.rawurlencode($this->plainToken);

        return (new MailMessage)
            ->subject('Kích hoạt tài khoản quản trị BookStore AI')
            ->line('Bạn được mời kích hoạt tài khoản quản trị BookStore AI.')
            ->line('Liên kết có hiệu lực 48 giờ và chỉ dùng một lần.')
            ->action('Đặt mật khẩu và kích hoạt', $url)
            ->line('Nếu bạn không yêu cầu, hãy bỏ qua email này.');
    }
}
