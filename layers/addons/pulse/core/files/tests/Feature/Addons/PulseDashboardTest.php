<?php

declare(strict_types=1);

namespace Tests\Feature\Addons;

use App\Infrastructure\Models\User;
use Tests\TestCase;

final class PulseDashboardTest extends TestCase
{
    public function test_guests_are_refused(): void
    {
        $this->get(route('pulse'))->assertForbidden();
    }

    public function test_a_listed_viewer_opens_the_dashboard(): void
    {
        config(['pulse.viewers' => ['viewer@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'Viewer@Example.com']))
            ->get(route('pulse'))
            ->assertOk();
    }

    public function test_a_user_who_is_not_a_viewer_is_refused(): void
    {
        config(['pulse.viewers' => ['viewer@example.com']]);

        $this->actingAs(User::factory()->create(['email' => 'someone@example.com']))
            ->get(route('pulse'))
            ->assertForbidden();
    }

    public function test_nobody_is_a_viewer_by_default(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('pulse'))
            ->assertForbidden();
    }
}
