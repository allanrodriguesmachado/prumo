<?php

namespace Tests;

use App\Models\Household;
use App\Models\User;
use App\Services\HouseholdService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // nenhuma chamada externa real durante os testes
        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 200)]);
    }

    /** Cria usuário com carteira (individual ou casal) e o autentica. */
    protected function userWithHousehold(string $type = 'individual', array $attrs = []): User
    {
        $user = User::factory()->create($attrs + ['onboarded_at' => now()]);
        app(HouseholdService::class)->createFor($user, $type);
        $this->actingAs($user->fresh());

        return $user->fresh();
    }

    protected function household(User $user): Household
    {
        return $user->currentHousehold()->first();
    }
}
