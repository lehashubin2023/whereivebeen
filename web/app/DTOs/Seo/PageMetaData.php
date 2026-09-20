<?php

namespace App\DTOs\Seo;

class PageMetaData
{
    /**
     * @param  array<string, mixed>|null  $schema
     */
    public function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly string $canonical,
        private readonly string $image,
        private readonly string $locale,
        private readonly bool $noindex,
        private readonly ?array $schema = null,
    ) {}

    /**
     * @return array{title: string, description: string, canonical: string, image: string, locale: string, noindex: bool, schema: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'canonical' => $this->canonical,
            'image' => $this->image,
            'locale' => $this->locale,
            'noindex' => $this->noindex,
            'schema' => $this->schema,
        ];
    }
}
