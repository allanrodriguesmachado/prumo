<?php

use App\Models\Asset;
use App\Models\User;
use App\Notifications\BillsDueNotification;
use App\Services\FinanceAnalytics;
use App\Services\Market\BcbClient;
use App\Services\Market\QuoteService;
use App\Services\RecurrenceService;
use App\Services\Telegram\TelegramBot;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schedule;

Artisan::command('prumo:recurrences', function (RecurrenceService $service) {
    $this->info('Lançamentos gerados: '.$service->generateAll());
})->purpose('Gera os lançamentos de salários, rendas e gastos fixos');

Artisan::command('prumo:market', function (BcbClient $bcb, QuoteService $quotes) {
    foreach (['cdi', 'selic', 'ipca', 'savings'] as $code) {
        $this->line("BCB {$code}: ".$bcb->sync($code).' registros');
    }
    $ok = 0;
    Asset::withoutGlobalScopes()->whereNotNull('ticker')->whereIn('class', ['stock', 'fii', 'etf', 'bdr', 'crypto'])->each(function ($a) use ($quotes, &$ok) {
        $ok += $quotes->refresh($a) ? 1 : 0;
    });
    $this->info("Cotações atualizadas: {$ok}");
})->purpose('Sincroniza indicadores do Banco Central e cotações');

Artisan::command('prumo:due-alerts', function (TelegramBot $bot) {
    User::whereNotNull('current_household_id')->with('currentHousehold')->each(function (User $user) use ($bot) {
        Auth::setUser($user);
        $items = (new FinanceAnalytics($user->currentHousehold))->upcoming(3)
            ->filter(fn ($i) => $i['type'] === 'expense')
            ->map(fn ($i) => ['label' => $i['label'], 'amount' => $i['amount'], 'date' => $i['date']->format('d/m'), 'overdue' => $i['overdue']])
            ->values()->all();
        if (! $items) {
            return;
        }
        $user->notify(new BillsDueNotification($items));
        if ($user->notify_telegram && $user->telegram_chat_id) {
            $bot->send($user->telegram_chat_id, "⏰ <b>Contas vencendo</b>\n".collect($items)->map(fn ($i) => "• {$i['date']} {$i['label']}: ".brl($i['amount']).($i['overdue'] ? ' ⚠️' : ''))->implode("\n"));
        }
    });
})->purpose('Avisa sobre contas a vencer (e-mail, Telegram e sininho)');

Artisan::command('prumo:telegram', function (TelegramBot $bot) {
    $this->info('Mensagens processadas: '.$bot->poll());
})->purpose('Lê mensagens do bot do Telegram (long polling)');

Artisan::command('prumo:weekly-review', function (TelegramBot $bot) {
    User::whereNotNull('current_household_id')->with('currentHousehold')->each(function (User $user) use ($bot) {
        Auth::setUser($user);
        $service = new \App\Services\WeeklyReview(new FinanceAnalytics($user->currentHousehold));
        $r = $service->build();
        if ($r['spent'] <= 0 && $r['earned'] <= 0) {
            return;
        }
        $user->notify(new \App\Notifications\WeeklyReviewNotification($r['headline'], $service->summaryText($r)));
        if ($user->notify_telegram && $user->telegram_chat_id) {
            $bot->send($user->telegram_chat_id, "🗓️ <b>Revisão da semana</b>\n".e($service->summaryText($r)));
        }
    });
})->purpose('Envia a revisão financeira semanal');

Artisan::command('prumo:debt-snapshots', function () {
    \App\Models\Household::each(fn ($h) => \App\Models\DebtSnapshot::capture($h));
})->purpose('Registra a evolução diária das dívidas (GPS Financeiro)');

Artisan::command('prumo:net-worth {--backfill}', function (\App\Services\NetWorthHistory $history) {
    $n = 0;
    \App\Models\Household::with('members')->each(function ($h) use ($history, &$n) {
        foreach ($h->members as $user) {
            if ($this->option('backfill')) {
                $history->backfill($user, $h);
            }
            $history->capture($user, $h);
            $n++;
        }
    });
    $this->info("Fotos de patrimônio registradas: {$n}");
})->purpose('Registra a evolução patrimonial diária de cada pessoa');

Schedule::command('prumo:net-worth')->dailyAt('23:55');
Schedule::command('prumo:weekly-review')->weeklyOn(1, '08:30');
Schedule::command('prumo:debt-snapshots')->dailyAt('23:50');
Schedule::command('prumo:recurrences')->dailyAt('00:10');
Schedule::command('prumo:market')->weekdays()->hourly()->between('9:00', '19:00')->withoutOverlapping();
Schedule::command('prumo:market')->dailyAt('06:30');
Schedule::command('prumo:due-alerts')->dailyAt('08:00');
Schedule::command('prumo:telegram')->everyMinute()->withoutOverlapping();
