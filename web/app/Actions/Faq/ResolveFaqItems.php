<?php

namespace App\Actions\Faq;

class ResolveFaqItems
{
    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public function exec(): array
    {
        $items = trans('faq.items');

        if (! is_array($items)) {
            return [];
        }

        $resolved = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['question'], $item['answer'])) {
                continue;
            }

            $resolved[] = [
                'question' => (string) $item['question'],
                'answer' => (string) $item['answer'],
            ];
        }

        return $resolved;
    }
}
