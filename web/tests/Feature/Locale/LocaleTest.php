<?php

namespace Tests\Feature\Locale;

use App\Enums\LocaleEnum;
use App\Http\Middleware\HandleLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function encoded(string $value): string
    {
        return trim((string) json_encode(
            $value,
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT,
        ), '"');
    }

    public function test_new_users_default_to_english()
    {
        $user = User::factory()->create();

        $this->assertSame(LocaleEnum::EN, $user->refresh()->locale);
    }

    public function test_the_root_template_ships_the_dictionary_once()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::RU]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('window.__locale = "ru"', false);
        $response->assertSee($this->encoded('Сессии'), false);
    }

    public function test_english_ships_an_empty_dictionary()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::EN]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('window.__locale = "en"', false);
        $response->assertSee('window.__translations = {}', false);
    }

    public function test_inertia_navigations_do_not_carry_the_dictionary()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::RU]);

        $response = $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', '1')
            ->get('/game-session/sessions');

        $response->assertDontSee('window.__translations', false);
        $response->assertDontSee($this->encoded('Сессии'), false);
    }

    public function test_the_remembered_language_wins_over_the_browser_language()
    {
        $response = $this->withUnencryptedCookie(HandleLocale::COOKIE, 'ru')
            ->withHeader('Accept-Language', 'en-US,en;q=0.9')
            ->get('/login');

        $response->assertOk();
        $response->assertSee('window.__locale = "ru"', false);
    }

    public function test_the_user_setting_wins_over_the_cookie()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::EN]);

        $this->actingAs($user)
            ->withUnencryptedCookie(HandleLocale::COOKIE, 'ru')
            ->get('/profile')
            ->assertOk()
            ->assertSee('window.__locale = "en"', false);
    }

    public function test_guests_fall_back_to_the_browser_language()
    {
        $response = $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9')
            ->get('/login');

        $response->assertOk();
        $response->assertSee('window.__locale = "ru"', false);
    }

    public function test_guests_without_a_known_language_get_english()
    {
        $response = $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')
            ->get('/login');

        $response->assertOk();
        $response->assertSee('window.__locale = "en"', false);
    }

    public function test_a_user_can_change_their_language()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::EN]);

        $this->actingAs($user)
            ->from('/profile')
            ->patch('/locale', ['locale' => 'ru'])
            ->assertRedirect('/profile')
            ->assertCookie(HandleLocale::COOKIE, 'ru', false);

        $this->assertSame(LocaleEnum::RU, $user->refresh()->locale);
    }

    public function test_the_language_must_be_supported()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson('/locale', ['locale' => 'de'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('locale');

        $this->assertSame(LocaleEnum::EN, $user->refresh()->locale);
    }

    public function test_guests_change_the_language_through_a_cookie()
    {
        $this->from('/')
            ->patch('/locale', ['locale' => 'ru'])
            ->assertRedirect('/')
            ->assertCookie(HandleLocale::COOKIE, 'ru', false);
    }

    public function test_admins_can_change_their_language_too()
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from('/profile')
            ->patch('/locale', ['locale' => 'ru'])
            ->assertRedirect('/profile');

        $this->assertSame(LocaleEnum::RU, $admin->refresh()->locale);
    }

    public function test_flash_messages_are_translated()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::RU]);

        $this->actingAs($user)
            ->patch('/profile', ['email' => 'igrok@example.com'])
            ->assertSessionHas('inertia.flash_data', [
                'toast' => [
                    'type' => 'success',
                    'message' => 'Профиль обновлён.',
                ],
            ]);
    }

    public function test_validation_messages_are_translated()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::RU]);

        $this->actingAs($user)
            ->patchJson('/profile', ['email' => 'not-an-email'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Поле email должно быть корректным адресом email.');
    }

    public function test_server_rendered_statistics_labels_are_translated()
    {
        $user = User::factory()->create(['locale' => LocaleEnum::RU]);

        $this->actingAs($user)
            ->get('/statistics')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics')
                ->where('overview.1.label', 'Точки маршрута')
                ->etc()
            );
    }
}
