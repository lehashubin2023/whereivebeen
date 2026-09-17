<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureRateLimiting();
    }

    protected function configureGates(): void
    {
        Gate::define('view-issue-reports', fn (User $user): bool => $user->isAdmin());
        Gate::define('manage-users', fn (User $user): bool => $user->isAdmin());
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('import-session', fn (Request $request) => Limit::perSecond(1, 5)
            ->by($this->throttleKey($request))
            ->response($this->throttled('game_session')));

        RateLimiter::for('import-file', fn (Request $request) => Limit::perMinute(1)
            ->by($this->throttleKey($request))
            ->response($this->throttled('file')));

        RateLimiter::for('issue-report', fn (Request $request) => Limit::perMinutes(5, 1)
            ->by($this->throttleKey($request))
            ->response($this->throttled('message')));

        RateLimiter::for('password-update', fn (Request $request) => Limit::perMinute(6)
            ->by($this->throttleKey($request))
            ->response($this->throttled('current_password')));
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    private function throttleKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }

    private function throttled(string $field): Closure
    {
        /**
         * @param  array<string, mixed>  $headers
         */
        $respond = function (Request $request, array $headers) use ($field): never {
            $seconds = (int) ($headers['Retry-After'] ?? 60);

            throw ValidationException::withMessages([
                $field => $seconds >= 60
                    ? __('Too many attempts. Try again in :minutes minutes.', [
                        'minutes' => (int) ceil($seconds / 60),
                    ])
                    : __('Too many attempts. Try again in :seconds seconds.', [
                        'seconds' => $seconds,
                    ]),
            ]);
        };

        return $respond;
    }
}
