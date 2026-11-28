<?php

declare(strict_types=1);

namespace Tests\Feature\Addons;

use App\Infrastructure\Filament\Pages\PulseDashboard;
use App\Infrastructure\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class PulseNavigationTest extends TestCase
{
    public function test_an_administrator_sees_the_pulse_link_and_opens_the_dashboard(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee(route('pulse'), false);
        $this->actingAs($admin)->get(route('pulse'))->assertOk();
    }

    public function test_the_admin_page_forwards_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/admin/pulse-dashboard')
            ->assertRedirect(route('pulse'));
    }

    public function test_the_link_is_hidden_when_the_gate_refuses(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        Gate::define('viewPulse', static fn (): bool => false);

        $this->get('/admin')->assertOk()->assertDontSee(route('pulse'), false);
        $this->assertFalse(PulseDashboard::canAccess());
    }
}
