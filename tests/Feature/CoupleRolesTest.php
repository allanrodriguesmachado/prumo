<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use App\Services\CoupleSettlement;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CoupleRolesTest extends TestCase
{
    use RefreshDatabase;

    private function couple(): array
    {
        $ana = $this->userWithHousehold('couple');
        $leo = User::factory()->create(['onboarded_at' => now()]);
        app(HouseholdService::class)->join($leo, $this->household($ana)->invite_code);

        return [$ana->fresh(), $leo->fresh(), $this->household($ana)];
    }

    public function test_owner_can_make_partner_read_only_and_viewer_cannot_write(): void
    {
        [$ana, $leo, $h] = $this->couple();
        $this->actingAs($ana)->put(route('couple.update'), ['name' => $h->name, 'type' => 'couple', 'split_mode' => 'equal', 'partner_role' => 'viewer'])->assertRedirect();
        $this->assertSame('viewer', $h->roleOf($leo));

        $this->actingAs($leo);
        $this->get(route('transactions.index'))->assertOk()->assertSee('somente leitura');
        $this->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'x', 'amount' => '10,00', 'date' => now()->toDateString()])
            ->assertSessionHas('error');
        $this->assertSame(0, Transaction::count());
        // ainda pode usar o consultor e o próprio perfil
        $this->postJson(route('ai.send'), ['message' => 'Quanto posso gastar hoje?'])->assertOk();
    }

    public function test_member_cannot_change_settings_or_roles(): void
    {
        [$ana, $leo, $h] = $this->couple();
        $this->actingAs($leo)->put(route('couple.update'), ['name' => 'Hack', 'type' => 'couple', 'split_mode' => 'equal'])->assertForbidden();
        $this->assertNotSame('Hack', $h->fresh()->name);
    }

    public function test_shared_expense_above_threshold_requires_partner_approval(): void
    {
        Notification::fake();
        [$ana, $leo, $h] = $this->couple();
        $h->putSetting('approval_threshold', 500);

        $this->actingAs($ana)->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'Sofá', 'amount' => '1.200,00', 'date' => now()->toDateString(), 'is_shared' => '1']);
        $this->actingAs($ana)->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'daily', 'description' => 'Mercado', 'amount' => '200,00', 'date' => now()->toDateString(), 'is_shared' => '1']);
        $sofa = Transaction::where('description', 'Sofá')->first();
        $this->assertSame('pending', $sofa->approval_status);
        $this->assertNull(Transaction::where('description', 'Mercado')->value('approval_status'));
        Notification::assertSentTo($leo, ApprovalRequestedNotification::class);

        // pendente não entra no acerto
        $s = (new CoupleSettlement($h))->forMonth(now());
        $this->assertEquals(200.0, $s['total_shared']);

        // quem lançou não aprova
        $this->actingAs($ana)->post(route('couple.approve', $sofa), ['decision' => 'approve'])->assertSessionHasErrors('approval');
        $this->actingAs($leo)->post(route('couple.approve', $sofa), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $sofa->fresh()->approval_status);
        $this->assertEquals(1400.0, (new CoupleSettlement($h))->forMonth(now())['total_shared']);
    }

    public function test_rejected_expense_becomes_individual(): void
    {
        [$ana, $leo, $h] = $this->couple();
        $h->putSetting('approval_threshold', 100);
        $this->actingAs($ana)->post(route('transactions.store'), ['type' => 'expense', 'kind' => 'installment', 'description' => 'Videogame', 'amount' => '3.000,00', 'installments' => 3, 'date' => now()->toDateString(), 'is_shared' => '1']);
        $first = Transaction::where('installment_number', 1)->first();
        $this->actingAs($leo)->post(route('couple.approve', $first), ['decision' => 'reject']);
        $this->assertSame(3, Transaction::where('approval_status', 'rejected')->where('is_shared', false)->count());
    }
}
