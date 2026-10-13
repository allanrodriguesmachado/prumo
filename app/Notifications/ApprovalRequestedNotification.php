<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public Transaction $transaction, public float $total) {}

    public function via(object $notifiable): array
    {
        return $notifiable->notify_email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $who = $this->transaction->user?->firstName() ?? 'Seu par';

        return (new MailMessage)->subject('Prumo: despesa do casal aguardando sua aprovação')
            ->greeting("Olá, {$notifiable->firstName()}!")
            ->line("{$who} lançou uma despesa compartilhada: {$this->transaction->description} — ".brl($this->total).'.')
            ->line('Ela só entra no acerto do casal depois que você aprovar.')
            ->action('Revisar', route('couple.index'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Despesa do casal para aprovar',
            'body' => ($this->transaction->user?->firstName() ?? 'Seu par').": {$this->transaction->description} (".brl($this->total).')',
            'url' => route('couple.index'),
        ];
    }
}
