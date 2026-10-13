<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyReviewNotification extends Notification
{
    use Queueable;

    public function __construct(public string $headline, public string $summary) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Prumo: sua revisão da semana')->greeting("Olá, {$notifiable->firstName()}!");
        foreach (explode("\n", $this->summary) as $line) {
            $mail->line($line);
        }

        return $mail->action('Ver revisão completa', route('weekly.index'));
    }

    public function toArray(object $notifiable): array
    {
        return ['title' => 'Revisão da semana', 'body' => $this->headline, 'url' => route('weekly.index')];
    }
}
