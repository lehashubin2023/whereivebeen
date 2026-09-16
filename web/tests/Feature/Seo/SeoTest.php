<?php

namespace Tests\Feature\Seo;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        config()->set('support.boosty', 'https://boosty.to/whereivebeen');
    }

    public function test_the_root_url_sends_guests_to_their_language()
    {
        $this->get('/')->assertRedirect('/en');

        $this->withHeader('Accept-Language', 'ru-RU,ru;q=0.9')
            ->get('/')
            ->assertRedirect('/ru');
    }

    public function test_unprefixed_pages_redirect_to_a_localized_url()
    {
        $this->get('/support')->assertRedirect('/en/support');
        $this->get('/faq')->assertRedirect('/en/faq');
    }

    public function test_an_unknown_locale_is_not_a_page()
    {
        $this->get('/de')->assertNotFound();
    }

    public function test_the_localized_page_carries_a_canonical_and_hreflang_links()
    {
        $response = $this->get('/ru');

        $response->assertOk();
        $response->assertSee('<link rel="canonical" href="'.route('home', ['locale' => 'ru']).'">', false);
        $response->assertSee('hreflang="en" href="'.route('home', ['locale' => 'en']).'"', false);
        $response->assertSee('hreflang="ru" href="'.route('home', ['locale' => 'ru']).'"', false);
        $response->assertSee('hreflang="x-default" href="'.route('home', ['locale' => 'en']).'"', false);
    }

    public function test_titles_are_translated_and_end_with_the_project_name()
    {
        $name = config()->string('app.name');

        $this->get('/en')
            ->assertSee('<title>'.trans('seo.home.title', [], 'en').' - '.$name.'</title>', false);

        $this->get('/en/faq')
            ->assertSee('<title>Addon FAQ - '.$name.'</title>', false);

        $this->get('/ru/faq')
            ->assertSee('<title>'.e(trans('seo.faq.title', [], 'ru')).' - '.$name.'</title>', false);
    }

    public function test_the_landing_page_ships_social_tags()
    {
        $response = $this->get('/en');

        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:image"', false);
        $response->assertSee('content="summary_large_image"', false);
        $response->assertSee('"@type":"SoftwareApplication"', false);
    }

    public function test_private_pages_are_not_indexed()
    {
        $response = $this->actingAs(User::factory()->create())->get('/statistics');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $response->assertDontSee('<link rel="canonical"', false);
    }

    public function test_the_faq_page_lists_questions_and_ships_its_schema()
    {
        $response = $this->get('/en/faq');

        $response->assertOk();
        $response->assertSee('"@type":"FAQPage"', false);
        $response->assertSee(e('Which World of Warcraft clients are supported?'), false);
    }

    public function test_the_sitemap_lists_every_public_page_in_both_languages()
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach (['home', 'faq', 'support'] as $route) {
            foreach (['en', 'ru'] as $locale) {
                $response->assertSee('<loc>'.route($route, ['locale' => $locale]).'</loc>', false);
            }
        }

        $response->assertSee('hreflang="x-default"', false);
    }

    public function test_the_sitemap_drops_the_support_page_when_no_donations_are_configured()
    {
        config()->set('support', ['boosty' => null, 'telegram' => null, 'crypto' => []]);

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertDontSee('<loc>'.route('support', ['locale' => 'en']).'</loc>', false);
        $response->assertSee('<loc>'.route('faq', ['locale' => 'en']).'</loc>', false);
    }

    public function test_robots_keeps_crawlers_away_outside_production()
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame("User-agent: *\nDisallow: /\n", $response->getContent());
    }

    public function test_robots_points_at_the_sitemap_in_production()
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertSee('Sitemap: '.route('sitemap'), false);
        $response->assertSee('Disallow: /admin', false);
        $response->assertSee('Disallow: /game-session', false);
    }
}
