<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_missing_page_renders_the_error_component()
    {
        $this->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 404));
    }

    public function test_a_forbidden_page_renders_the_error_component()
    {
        Route::middleware('web')->get('/forbidden-probe', fn () => abort(403));

        $this->get('/forbidden-probe')
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 403));
    }

    public function test_a_throttled_page_renders_the_error_component()
    {
        Route::middleware('web')->get('/throttled-probe', fn () => abort(429));

        $this->get('/throttled-probe')
            ->assertStatus(429)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 429));
    }

    public function test_a_middleware_throttle_renders_the_error_component()
    {
        Route::middleware(['web', 'throttle:1,1'])->get('/limited-probe', fn () => 'ok');

        $this->get('/limited-probe')->assertOk();

        $this->get('/limited-probe')
            ->assertStatus(429)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 429));
    }

    public function test_a_server_error_renders_the_error_component_without_debug()
    {
        config(['app.debug' => false]);

        Route::middleware('web')->get('/broken-probe', fn () => abort(500));

        $this->get('/broken-probe')
            ->assertStatus(500)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Error')
                ->where('status', 500));
    }

    public function test_a_server_error_is_left_alone_while_debugging()
    {
        config(['app.debug' => true]);

        Route::middleware('web')->get('/broken-probe', fn () => abort(500));

        $this->get('/broken-probe')
            ->assertStatus(500)
            ->assertHeaderMissing('X-Inertia');
    }

    public function test_an_expired_page_sends_the_user_back()
    {
        Route::middleware('web')->get('/expired-probe', fn () => abort(419));

        $this->from('/faq')
            ->get('/expired-probe')
            ->assertRedirect('/faq');
    }

    public function test_an_api_request_keeps_its_json_response()
    {
        $this->getJson('/no-such-page')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }

    public function test_the_error_page_is_not_indexable()
    {
        $this->get('/no-such-page')
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.noindex', true));
    }

    public function test_an_authenticated_user_also_gets_the_error_page()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/no-such-page')
            ->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('Error'));
    }
}
