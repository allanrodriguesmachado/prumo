<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_factor_full_flow(): void
    {
        $user = $this->userWithHousehold('individual', ['password' => 'segredo123']);
        $this->get(route('two-factor.setup'))->assertOk()->assertSee('<svg', false);
        $secret = session('2fa.pending_secret');
        $g = new Google2FA;

        $this->post(route('two-factor.enable'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.enable'), ['code' => $g->getCurrentOtp($secret)])->assertRedirect(route('profile.edit'));
        $user->refresh();
        $this->assertTrue($user->hasTwoFactor());
        $this->assertCount(8, $user->two_factor_recovery_codes);
        // segredo fica criptografado no banco
        $this->assertStringNotContainsString($secret, DB::table('users')->where('id', $user->id)->value('two_factor_secret'));

        $this->post('/sair');
        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post(route('two-factor.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $recovery = $user->two_factor_recovery_codes[0];
        $this->post(route('two-factor.verify'), ['recovery_code' => $recovery])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes, 'código de recuperação é de uso único');
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'event' => 'login_recovery_code']);
    }

    public function test_challenge_requires_pending_login(): void
    {
        $this->get(route('two-factor.challenge'))->assertForbidden();
    }

    public function test_private_items_are_hidden_from_partner(): void
    {
        $ana = $this->userWithHousehold('couple');
        $private = Account::create(['name' => 'Conta secreta da Ana', 'is_private' => true]);
        $this->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'Presente surpresa', 'amount' => '300,00', 'date' => now()->toDateString(), 'method' => 'account:'.$private->id, 'is_shared' => '1']);
        $tx = Transaction::where('description', 'Presente surpresa')->first();
        $this->assertTrue($tx->is_private, 'lançamento em conta privada herda a privacidade');
        $this->assertFalse($tx->is_shared, 'item privado nunca entra no rateio do casal');

        $leo = User::factory()->create();
        app(HouseholdService::class)->join($leo, $this->household($ana)->invite_code);
        $this->actingAs($leo->fresh());

        $this->get(route('accounts.index'))->assertOk()->assertDontSee('Conta secreta da Ana');
        $this->get(route('transactions.index'))->assertOk()->assertDontSee('Presente surpresa');
        $this->get(route('transactions.edit', $tx))->assertNotFound();
        $this->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'x', 'amount' => '1,00', 'date' => now()->toDateString(), 'method' => 'account:'.$private->id])
            ->assertSessionHasErrors('account_id');
    }

    public function test_financial_changes_are_audited_without_sensitive_notes(): void
    {
        $this->userWithHousehold();
        $this->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'Farmácia', 'amount' => '50,00', 'date' => now()->toDateString(), 'notes' => 'remédio controlado']);
        $t = Transaction::first();
        $this->assertSame('remédio controlado', $t->notes);
        $this->assertStringNotContainsString('remédio', DB::table('transactions')->value('notes'), 'observação criptografada');
        $log = AuditLog::where('event', 'created')->where('auditable_type', 'Transaction')->first();
        $this->assertNotNull($log);
        $this->assertArrayNotHasKey('notes', $log->changes);
        $this->delete(route('transactions.destroy', $t));
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_type' => 'Transaction', 'auditable_id' => $t->id]);
    }

    public function test_security_headers_and_no_store(): void
    {
        $this->userWithHousehold();
        $res = $this->get(route('dashboard'));
        $res->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }

    public function test_lgpd_export_contains_only_own_household(): void
    {
        $this->userWithHousehold();
        Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'De outra pessoa', 'amount' => 10, 'date' => now()->toDateString()]);
        $this->userWithHousehold();
        Transaction::create(['type' => 'expense', 'kind' => 'daily', 'description' => 'Meu gasto', 'amount' => 10, 'date' => now()->toDateString()]);
        $json = $this->get(route('profile.export'))->assertOk()->streamedContent();
        $this->assertStringContainsString('Meu gasto', $json);
        $this->assertStringNotContainsString('De outra pessoa', $json);
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create();
        foreach (range(1, 10) as $i) {
            $this->post('/entrar', ['email' => $user->email, 'password' => 'errada'.$i]);
        }
        $this->post('/entrar', ['email' => $user->email, 'password' => 'errada'])->assertStatus(429);
    }
}
