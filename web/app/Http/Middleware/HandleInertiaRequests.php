<?php

namespace App\Http\Middleware;

use App\Actions\Seo\BuildPageMeta;
use App\Actions\Support\ResolveSupportChannels;
use App\Enums\LocaleEnum;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function __construct(
        private readonly BuildPageMeta $buildPageMeta,
        private readonly ResolveSupportChannels $supportChannels,
    ) {}

    /**
     * @var string
     */
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'backgroundMap' => $this->randomMapUrl(),
            'meta' => $this->buildPageMeta->exec($request)->toArray(),
            'supportAvailable' => $this->supportChannels->available(),
            'locale' => app()->getLocale(),
            'locales' => LocaleEnum::options(),
        ];
    }

    private function randomMapUrl(): string
    {
        $maps = glob(public_path('maps/*.png')) ?: [];

        if ($maps === []) {
            return '/maps/Hellfire_Peninsula.png';
        }

        return '/maps/'.basename($maps[array_rand($maps)]);
    }
}
