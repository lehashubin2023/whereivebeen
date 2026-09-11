<?php

namespace App\Actions\Support;

class ResolveSupportChannels
{
    /**
     * @return array{boosty: string|null, telegram: string|null, crypto: array<int, array{label: string, address: string}>}
     */
    public function exec(): array
    {
        return [
            'boosty' => $this->link('support.boosty'),
            'telegram' => $this->link('support.telegram'),
            'crypto' => $this->wallets(),
        ];
    }

    private function link(string $key): ?string
    {
        $value = config($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, array{label: string, address: string}>
     */
    private function wallets(): array
    {
        $configured = config('support.crypto');

        if (! is_array($configured)) {
            return [];
        }

        $wallets = [];

        foreach ($configured as $label => $address) {
            if (! is_string($address)) {
                continue;
            }

            $address = trim($address);

            if ($address === '') {
                continue;
            }

            $wallets[] = [
                'label' => (string) $label,
                'address' => $address,
            ];
        }

        return $wallets;
    }
}
