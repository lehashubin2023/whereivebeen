<?php

namespace App\Actions\Seo;

use App\Enums\LocaleEnum;

class BuildSitemap
{
    public function exec(): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
        ];

        foreach ($this->urls() as $url) {
            $lines[] = '    <url>';
            $lines[] = '        <loc>'.$this->escape($url['loc']).'</loc>';

            foreach ($url['alternates'] as $hreflang => $href) {
                $lines[] = '        <xhtml:link rel="alternate" hreflang="'.$this->escape($hreflang).'" href="'.$this->escape($href).'"/>';
            }

            $lines[] = '    </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<int, array{loc: string, alternates: array<string, string>}>
     */
    public function urls(): array
    {
        $urls = [];

        foreach ($this->routes() as $route) {
            $alternates = [];

            foreach (LocaleEnum::cases() as $locale) {
                $alternates[$locale->value] = route($route, ['locale' => $locale->value]);
            }

            $alternates['x-default'] = route($route, ['locale' => LocaleEnum::default()->value]);

            foreach (LocaleEnum::cases() as $locale) {
                $urls[] = [
                    'loc' => $alternates[$locale->value],
                    'alternates' => $alternates,
                ];
            }
        }

        return $urls;
    }

    /**
     * @return array<int, string>
     */
    private function routes(): array
    {
        return array_values(array_filter(
            config()->array('seo.indexable'),
            fn (mixed $name): bool => is_string($name),
        ));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
