<?php

namespace Tests\Feature\Support;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupportPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('support', [
            'boosty' => 'https://boosty.to/whereivebeen',
            'telegram' => 'https://t.me/whereivebeen_bot',
            'crypto' => [
                'USDT (TRC-20)' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
                'TON' => '',
            ],
        ]);
    }

    public function test_guests_can_open_the_support_page()
    {
        $this->get('/support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Support')
                ->has('channels')
            );
    }

    public function test_authenticated_users_can_open_the_support_page()
    {
        $this->actingAs(User::factory()->create())
            ->get('/support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Support'));
    }

    public function test_admins_can_open_the_support_page()
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Support'));
    }

    public function test_configured_channels_reach_the_page()
    {
        $this->get('/support')
            ->assertInertia(fn (Assert $page) => $page
                ->where('channels.boosty', 'https://boosty.to/whereivebeen')
                ->where('channels.telegram', 'https://t.me/whereivebeen_bot')
                ->where('channels.crypto', [
                    ['label' => 'USDT (TRC-20)', 'address' => 'TXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
                ])
                ->etc()
            );
    }

    public function test_blank_channels_are_filtered_out()
    {
        config()->set('support', [
            'boosty' => '',
            'telegram' => null,
            'crypto' => [
                'TON' => '   ',
            ],
        ]);

        $this->get('/support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('channels.boosty', null)
                ->where('channels.telegram', null)
                ->where('channels.crypto', [])
                ->etc()
            );
    }

    public function test_the_page_renders_when_nothing_is_configured()
    {
        config()->set('support', [
            'boosty' => null,
            'telegram' => null,
            'crypto' => [],
        ]);

        $this->get('/support')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Support'));
    }
}
