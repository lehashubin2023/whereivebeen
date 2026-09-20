<?php

namespace Tests\Feature\Addon;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AddonPageTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->user = User::factory()->create();
    }

    public function test_guest_sees_the_landing_page()
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Welcome')
                ->has('addon.available')
            );
    }

    public function test_authenticated_user_is_redirected_from_the_landing_page()
    {
        $this->actingAs($this->user)
            ->get('/')
            ->assertRedirect('/game-session/sessions');
    }

    public function test_guest_is_redirected_from_the_addon_page()
    {
        $this->get('/addon')->assertRedirect('/login');
    }

    public function test_addon_page_renders()
    {
        $this->actingAs($this->user)
            ->get('/addon')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Addon')
                ->has('addon')
            );
    }
}
