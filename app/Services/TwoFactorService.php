<?php

namespace App\Services;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/** Autenticação em dois fatores por TOTP (Google Authenticator, Authy, 1Password...). */
class TwoFactorService
{
    public function __construct(private Google2FA $engine = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->engine->generateSecretKey(32);
    }

    public function qrSvg(User $user, string $secret): string
    {
        $uri = $this->engine->getQRCodeUrl(config('app.name'), $user->email, $secret);
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($uri);
    }

    /** Valida o código e impede reutilização do mesmo código (replay). */
    public function verify(User $user, string $code, ?string $secret = null): bool
    {
        $secret ??= $user->two_factor_secret;
        $code = preg_replace('/\D/', '', $code);
        if (! $secret || strlen($code) !== 6) {
            return false;
        }
        $key = "2fa:used:{$user->id}:{$code}";
        if (Cache::has($key)) {
            return false;
        }
        $ok = (bool) $this->engine->verifyKey($secret, $code, 1);
        if ($ok) {
            Cache::put($key, true, now()->addMinutes(3));
        }

        return $ok;
    }

    public function newRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))->all();
    }

    /** Consome um código de recuperação (uso único). */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = Str::upper(trim($code));
        if (! in_array($code, $codes, true)) {
            return false;
        }
        $user->forceFill(['two_factor_recovery_codes' => array_values(array_diff($codes, [$code]))])->save();

        return true;
    }
}
