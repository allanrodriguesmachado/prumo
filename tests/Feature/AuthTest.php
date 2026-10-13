<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_and_redirects_to_onboarding(): void
    {
        $this->post('/cadastro', [
            'name' => 'Ana Souza', 'email' => 'ana@example.com',
            'password' => 'segredo123', 'password_confirmation' => 'segredo123', 'terms' => '1',
        ])->assertRedirect(route('onboarding'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'ana@example.com']);
    }

    public function test_weak_password_is_rejected(): void
    {
        $this->post('/cadastro', [
            'name' => 'Ana', 'email' => 'ana@example.com', 'password' => '123', 'password_confirmation' => '123', 'terms' => '1',
        ])->assertSessionHasErrors('password');
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'segredo123']);
        $this->post('/entrar', ['email' => $user->email, 'password' => 'errada'])->assertSessionHasErrors('email');
        $this->post('/entrar', ['email' => $user->email, 'password' => 'segredo123'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        $this->post('/sair')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/painel')->assertRedirect('/entrar');
    }

    public function test_user_without_household_goes_to_onboarding(): void
    {
        $this->actingAs(User::factory()->create())->get('/painel')->assertRedirect(route('onboarding'));
    }

    public function test_onboarding_creates_household_with_default_categories(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/comecar', ['type' => 'couple'])->assertRedirect(route('setup'));
        $user->refresh();
        $this->assertNotNull($user->current_household_id);
        $this->assertSame('couple', $user->currentHousehold->type);
        $this->assertGreaterThan(20, $user->currentHousehold->categories()->count());
    }
}
