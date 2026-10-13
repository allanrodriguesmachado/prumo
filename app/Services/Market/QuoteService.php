<?php

namespace App\Services\Market;

use App\Models\Asset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cotações gratuitas:
 *  - B3 (ações, FIIs, ETFs, BDRs): brapi.dev
 *  - Cripto: CoinGecko (API pública)
 *  - Moedas: AwesomeAPI
 */
class QuoteService
{
    public const CRYPTO_IDS = [
        'BTC' => 'bitcoin', 'ETH' => 'ethereum', 'SOL' => 'solana', 'USDT' => 'tether', 'USDC' => 'usd-coin',
        'BNB' => 'binancecoin', 'XRP' => 'ripple', 'ADA' => 'cardano', 'DOGE' => 'dogecoin', 'DOT' => 'polkadot',
        'MATIC' => 'matic-network', 'POL' => 'polygon-ecosystem-token', 'LINK' => 'chainlink', 'LTC' => 'litecoin',
        'AVAX' => 'avalanche-2', 'TRX' => 'tron', 'SHIB' => 'shiba-inu', 'TON' => 'the-open-network',
    ];

    public function stock(string $ticker): ?array
    {
        $ticker = strtoupper(trim($ticker));

        return Cache::remember("quote:b3:{$ticker}", now()->addMinutes(20), function () use ($ticker) {
            try {
                $params = array_filter(['token' => config('prumo.brapi_token')]);
                $res = Http::timeout(15)->retry(2, 400)->acceptJson()->get("https://brapi.dev/api/quote/{$ticker}", $params);
                $r = $res->json('results.0');
                if (! $res->ok() || ! $r || ! isset($r['regularMarketPrice'])) {
                    return null;
                }

                return [
                    'price' => (float) $r['regularMarketPrice'],
                    'change' => (float) ($r['regularMarketChangePercent'] ?? 0),
                    'name' => $r['longName'] ?? $r['shortName'] ?? $ticker,
                    'logo' => $r['logourl'] ?? null,
                ];
            } catch (\Throwable $e) {
                Log::info("brapi {$ticker}: ".$e->getMessage());

                return null;
            }
        });
    }

    public function cryptoId(string $symbol): string
    {
        $s = strtoupper(trim($symbol));

        return self::CRYPTO_IDS[$s] ?? strtolower(trim($symbol));
    }

    public function crypto(array $symbols): array
    {
        $ids = array_unique(array_map(fn ($s) => $this->cryptoId($s), $symbols));
        if (! $ids) {
            return [];
        }
        sort($ids);

        return Cache::remember('quote:crypto:'.md5(implode(',', $ids)), now()->addMinutes(10), function () use ($ids) {
            try {
                $res = Http::timeout(15)->retry(2, 500)->acceptJson()->get('https://api.coingecko.com/api/v3/simple/price', [
                    'ids' => implode(',', $ids), 'vs_currencies' => 'brl', 'include_24hr_change' => 'true',
                ]);

                return collect($res->json() ?? [])->map(fn ($v) => ['price' => (float) ($v['brl'] ?? 0), 'change' => (float) ($v['brl_24h_change'] ?? 0)])->all();
            } catch (\Throwable $e) {
                Log::info('coingecko: '.$e->getMessage());

                return [];
            }
        });
    }

    public function currencies(): array
    {
        return Cache::remember('quote:fx', now()->addMinutes(30), function () {
            try {
                $res = Http::timeout(10)->retry(2, 400)->acceptJson()->get('https://economia.awesomeapi.com.br/json/last/USD-BRL,EUR-BRL,BTC-BRL');
                if (! $res->ok()) {
                    return [];
                }

                return collect($res->json())->map(fn ($v) => ['bid' => (float) $v['bid'], 'change' => (float) $v['pctChange'], 'name' => $v['name']])->all();
            } catch (\Throwable $e) {
                return [];
            }
        });
    }

    /** Atualiza o preço de um ativo de mercado. */
    public function refresh(Asset $asset): bool
    {
        if (! $asset->isMarketPriced()) {
            return false;
        }
        if ($asset->class === 'crypto') {
            $q = $this->crypto([$asset->ticker])[$this->cryptoId($asset->ticker)] ?? null;
        } else {
            $q = $this->stock($asset->ticker);
        }
        if (! $q || $q['price'] <= 0) {
            return false;
        }
        $asset->forceFill(['last_price' => $q['price'], 'day_change' => $q['change'], 'price_updated_at' => now()])->save();

        return true;
    }

    /** Lista de tickers da B3 para autocomplete (gratuito na brapi). */
    public function searchTickers(string $term): array
    {
        $term = strtoupper(trim($term));
        if (strlen($term) < 2) {
            return [];
        }

        return Cache::remember("brapi:search:{$term}", now()->addDay(), function () use ($term) {
            try {
                $res = Http::timeout(10)->acceptJson()->get('https://brapi.dev/api/available', array_filter(['search' => $term, 'token' => config('prumo.brapi_token')]));

                return array_slice($res->json('stocks') ?? [], 0, 15);
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
