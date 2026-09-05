<?php

namespace App\Actions\GameSession;

use App\Enums\GameSession\EventTypeEnum;

class DescribeEvent
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    public function exec(EventTypeEnum $type, array $payload): array
    {
        return match ($type) {
            EventTypeEnum::MOUNT => $this->mount($payload),
            EventTypeEnum::TAXI => $this->taxi($payload),
            EventTypeEnum::LEVELUP => $this->levelUp($payload),
            EventTypeEnum::LOOT => $this->loot($payload),
            EventTypeEnum::VISIT => $this->visit($payload),
            EventTypeEnum::GROUP => $this->group($payload),
            EventTypeEnum::QUEST => $this->quest($payload),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function mount(array $payload): array
    {
        return [
            $this->row('State', ($payload['mounted'] ?? false) ? 'Mounted' : 'Dismounted'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function taxi(array $payload): array
    {
        return [
            $this->row('State', ($payload['on_taxi'] ?? false) ? 'Takeoff' : 'Landing'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function levelUp(array $payload): array
    {
        if (! isset($payload['level'])) {
            return [];
        }

        return [$this->row('Level', (string) $payload['level'])];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function loot(array $payload): array
    {
        $item = $payload['item_name'] ?? $payload['item_id'] ?? null;
        $rows = [];

        if ($item !== null) {
            $rows[] = $this->row('Item', (string) $item);
        }

        if (isset($payload['count'])) {
            $rows[] = $this->row('Count', (string) $payload['count']);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function visit(array $payload): array
    {
        if (! isset($payload['place'])) {
            return [];
        }

        return [$this->row('Place', ucfirst((string) $payload['place']))];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function group(array $payload): array
    {
        $rows = [];

        if (isset($payload['action'])) {
            $rows[] = $this->row(
                'Action',
                $payload['action'] === 'join' ? 'Joined' : 'Left',
            );
        }

        if (isset($payload['member'])) {
            $rows[] = $this->row('Member', (string) $payload['member']);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function quest(array $payload): array
    {
        $rows = [];

        if (isset($payload['action'])) {
            $rows[] = $this->row(
                'Action',
                $payload['action'] === 'accept' ? 'Accepted' : 'Turned in',
            );
        }

        $quest = $payload['title'] ?? $payload['quest_id'] ?? null;

        if ($quest !== null) {
            $rows[] = $this->row('Quest', (string) $quest);
        }

        return $rows;
    }

    /**
     * @return array{label: string, value: string}
     */
    private function row(string $label, string $value): array
    {
        return ['label' => $label, 'value' => $value];
    }
}
