<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BillsDueNotification extends Notification
{
    use Queueable;

    public function __construct(public array $items) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Prumo: contas vencendo')->greeting("Olá, {$notifiable->firstName()}!")
            ->line('Estas contas vencem nos próximos dias:');
        foreach ($this->items as $i) {
            $mail->line("• {$i['date']} — {$i['label']}: ".brl($i['amount']).($i['overdue'] ? ' (VENCIDA)' : ''));
        }

        return $mail->action('Abrir o Prumo', route('calendar.index'))->line('Pagar em dia evita multa, juros e protege seu score.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => count($this->items).' conta(s) vencendo',
            'body' => collect($this->items)->take(3)->map(fn ($i) => "{$i['label']} ({$i['date']})")->implode(', '),
            'url' => route('calendar.index'),
        ];
    }
}
