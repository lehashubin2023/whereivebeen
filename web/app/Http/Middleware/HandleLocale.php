<?php

namespace App\Http\Middleware;

use App\Enums\LocaleEnum;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleLocale
{
    public const COOKIE = 'locale';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        app()->setLocale($locale->value);

        View::share('locale', $locale->value);
        View::share('ogLocale', $locale->tag());
        View::share('translations', $this->translations($locale));

        $fromUrl = $this->fromUrl($request);

        if ($fromUrl instanceof LocaleEnum && $request->cookie(self::COOKIE) !== $fromUrl->value) {
            Cookie::queue(self::COOKIE, $fromUrl->value, 60 * 24 * 365);
        }

        return $next($request);
    }

    private function resolve(Request $request): LocaleEnum
    {
        $fromUrl = $this->fromUrl($request);

        if ($fromUrl instanceof LocaleEnum) {
            return $fromUrl;
        }

        $user = $request->user();

        if ($user instanceof User) {
            return $user->locale;
        }

        return $this->fromCookie($request) ?? $this->preferred($request);
    }

    private function fromUrl(Request $request): ?LocaleEnum
    {
        $locale = $request->route('locale');

        return is_string($locale) ? LocaleEnum::tryFrom($locale) : null;
    }

    private function fromCookie(Request $request): ?LocaleEnum
    {
        $locale = $request->cookie(self::COOKIE);

        return is_string($locale) ? LocaleEnum::tryFrom($locale) : null;
    }

    private function preferred(Request $request): LocaleEnum
    {
        $preferred = $request->getPreferredLanguage(LocaleEnum::values());

        return LocaleEnum::tryFrom((string) $preferred) ?? LocaleEnum::default();
    }

    /**
     * @return array<string, string>
     */
    private function translations(LocaleEnum $locale): array
    {
        $path = lang_path($locale->value.'.json');

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        if (! is_array($decoded)) {
            return [];
        }

        /** @var array<string, string> $decoded */
        return $decoded;
    }
}
