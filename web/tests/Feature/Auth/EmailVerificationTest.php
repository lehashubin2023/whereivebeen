<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        $this->skipUnlessFortifyHas(Features::emailVerification());
    }

    public function test_registration_sends_a_verification_email()
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'email' => 'fresh@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
        ]);

        $user = User::where('email', 'fresh@example.com')->firstOrFail();

        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_the_notice_screen_can_be_rendered()
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/VerifyEmail'));
    }

    public function test_unverified_users_are_sent_to_the_notice_screen()
    {
        $this->actingAs(User::factory()->unverified()->create())
            ->get(route('game-session.sessions'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_admins_are_sent_to_the_notice_screen()
    {
        $this->actingAs(User::factory()->admin()->unverified()->create())
            ->get(route('admin.issue-reports.index'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_users_can_still_fix_their_email()
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('profile.edit'))->assertOk();

        $this->actingAs($user)
            ->patch(route('profile.update'), ['email' => 'corrected@example.com'])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('corrected@example.com', $user->refresh()->email);
    }

    public function test_verified_users_are_not_held_at_the_notice_screen()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('verification.notice'))
            ->assertRedirect(config('fortify.home'));
    }

    public function test_the_email_can_be_verified()
    {
        Event::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->verificationUrl($user))->assertRedirect();

        Event::assertDispatched(Verified::class);
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_the_email_is_not_verified_with_an_invalid_hash()
    {
        $user = User::factory()->unverified()->create();

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1('wrong@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($user->refresh()->email_verified_at);
    }

    public function test_the_verification_email_can_be_resent()
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->post(route('verification.send'))
            ->assertRedirect();

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_guests_cannot_reach_the_notice_screen()
    {
        $this->get(route('verification.notice'))->assertRedirect(route('login'));
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }
}
