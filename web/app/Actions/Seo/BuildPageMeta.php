<?php

namespace App\Actions\Seo;

use App\Actions\Faq\ResolveFaqItems;
use App\DTOs\Seo\PageMetaData;
use App\Enums\LocaleEnum;
use Illuminate\Http\Request;

class BuildPageMeta
{
    public function __construct(
        private readonly ResolveFaqItems $resolveFaqItems,
    ) {}

    public function exec(Request $request): PageMetaData
    {
        $locale = LocaleEnum::tryFrom(app()->getLocale()) ?? LocaleEnum::default();
        $route = $this->indexableRoute($request);
        $copy = $this->copy($route ?? 'default');

        return new PageMetaData(
            title: $this->title($copy['title']),
            description: $copy['description'],
            canonical: $route !== null ? route($route) : $request->url(),
            image: url(config()->string('seo.og_image')),
            locale: $locale->value,
            noindex: $route === null,
            schema: $route !== null ? $this->schema($route, $copy) : null,
        );
    }

    private function indexableRoute(Request $request): ?string
    {
        $route = $request->route()?->getName();

        if (! is_string($route) || ! in_array($route, $this->indexable(), true)) {
            return null;
        }

        return $route;
    }

    /**
     * @return array<int, string>
     */
    private function indexable(): array
    {
        return array_values(array_filter(
            config()->array('seo.indexable'),
            fn (mixed $name): bool => is_string($name),
        ));
    }

    private function title(string $title): string
    {
        $name = config()->string('app.name');

        return $title === '' ? $name : $title.' - '.$name;
    }

    /**
     * @return array{title: string, description: string}
     */
    private function copy(string $key): array
    {
        $copy = trans('seo.'.$key);

        if (! is_array($copy) || ! isset($copy['title'], $copy['description'])) {
            $copy = trans('seo.default');
        }

        if (! is_array($copy) || ! isset($copy['title'], $copy['description'])) {
            return ['title' => '', 'description' => ''];
        }

        return [
            'title' => (string) $copy['title'],
            'description' => (string) $copy['description'],
        ];
    }

    /**
     * @param  array{title: string, description: string}  $copy
     * @return array<string, mixed>|null
     */
    private function schema(string $route, array $copy): ?array
    {
        return match ($route) {
            'home' => [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => config()->string('app.name'),
                'description' => $copy['description'],
                'applicationCategory' => 'GameApplication',
                'operatingSystem' => 'World of Warcraft',
                'url' => route('home'),
                'offers' => [
                    '@type' => 'Offer',
                    'price' => '0',
                    'priceCurrency' => 'USD',
                ],
            ],
            'faq' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(
                    fn (array $item): array => [
                        '@type' => 'Question',
                        'name' => $item['question'],
                        'acceptedAnswer' => [
                            '@type' => 'Answer',
                            'text' => $item['answer'],
                        ],
                    ],
                    $this->resolveFaqItems->exec(),
                ),
            ],
            default => null,
        };
    }
}
