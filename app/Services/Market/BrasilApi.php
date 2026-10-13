<?php

namespace App\Services\Market;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** BrasilAPI — gratuita e sem chave: bancos e feriados nacionais. */
class BrasilApi
{
    public function banks(): array
    {
        return Cache::remember('brasilapi:banks', now()->addWeek(), function () {
            try {
                $res = Http::timeout(15)->retry(2, 500)->get('https://brasilapi.com.br/api/banks/v1');

                return collect($res->json() ?? [])->filter(fn ($b) => ! empty($b['code']) && ! empty($b['name']))
                    ->map(fn ($b) => ['code' => (string) $b['code'], 'name' => $b['name']])
                    ->sortBy('code')->values()->all();
            } catch (\Throwable) {
                return [];
            }
        }) ?: $this->fallbackBanks();
    }

    public function holidays(int $year): array
    {
        return Cache::remember("brasilapi:holidays:{$year}", now()->addMonth(), function () use ($year) {
            try {
                return Http::timeout(10)->get("https://brasilapi.com.br/api/feriados/v1/{$year}")->json() ?? [];
            } catch (\Throwable) {
                return [];
            }
        });
    }

    private function fallbackBanks(): array
    {
        return [
            ['code' => '1', 'name' => 'Banco do Brasil'], ['code' => '33', 'name' => 'Santander'], ['code' => '77', 'name' => 'Banco Inter'],
            ['code' => '104', 'name' => 'Caixa Econômica Federal'], ['code' => '197', 'name' => 'Stone'], ['code' => '208', 'name' => 'BTG Pactual'],
            ['code' => '237', 'name' => 'Bradesco'], ['code' => '260', 'name' => 'Nubank'], ['code' => '290', 'name' => 'PagBank'],
            ['code' => '323', 'name' => 'Mercado Pago'], ['code' => '336', 'name' => 'C6 Bank'], ['code' => '341', 'name' => 'Itaú'],
            ['code' => '380', 'name' => 'PicPay'], ['code' => '403', 'name' => 'Cora'], ['code' => '748', 'name' => 'Sicredi'], ['code' => '756', 'name' => 'Sicoob'],
        ];
    }
}
