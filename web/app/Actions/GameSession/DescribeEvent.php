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
            EventTypeEnum::ZONE => $this->zone($payload),
            EventTypeEnum::GATHER => $this->gather($payload),
            EventTypeEnum::DEATH => $this->death($payload),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function zone(array $payload): array
    {
        $rows = [];

        if (isset($payload['zone'])) {
            $rows[] = $this->row('Zone', (string) $payload['zone']);
        }

        if (isset($payload['sub_zone'])) {
            $rows[] = $this->row('Subzone', (string) $payload['sub_zone']);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function gather(array $payload): array
    {
        $rows = [];
        $node = (array) ($payload['node'] ?? []);

        if (isset($node['name'])) {
            $rows[] = $this->row('Node', (string) $node['name']);
        }

        if (isset($node['prof'])) {
            $rows[] = $this->row('Profession', ucfirst((string) $node['prof']));
        }

        return array_merge($rows, $this->items($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function death(array $payload): array
    {
        if (isset($payload['environment'])) {
            return [$this->row('Cause', ucfirst(strtolower((string) $payload['environment'])))];
        }

        $killer = (array) ($payload['killer'] ?? []);

        if ($killer === []) {
            return [];
        }

        $rows = [];

        if (isset($killer['name'])) {
            $rows[] = $this->row(
                ($killer['pvp'] ?? false) ? 'Killed by player' : 'Killed by',
                (string) $killer['name'],
            );
        }

        if (isset($killer['class'])) {
            $rows[] = $this->row('Class', ucfirst(strtolower((string) $killer['class'])));
        } elseif (isset($killer['creatureType'])) {
            $rows[] = $this->row('Creature type', (string) $killer['creatureType']);
        }

        if (isset($killer['spell'])) {
            $rows[] = $this->row('Ability', (string) $killer['spell']);
        }

        if (isset($killer['amount'])) {
            $rows[] = $this->row('Damage', (string) $killer['amount']);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function items(array $payload): array
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items) || $items === []) {
            return [];
        }

        return array_values(array_map(
            fn (array $item) => $this->row(
                'Item',
                sprintf('%s x%s', $item['name'] ?? $item['id'] ?? '?', $item['n'] ?? 1),
            ),
            array_filter($items, 'is_array'),
        ));
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
        return $this->items($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function visit(array $payload): array
    {
        $places = $payload['places'] ?? null;

        if (! is_array($places) || $places === []) {
            return [];
        }

        $rows = [$this->row(
            'Places',
            implode(', ', array_map(fn ($place) => ucfirst((string) $place), $places)),
        )];

        if (isset($payload['npc_name'])) {
            $rows[] = $this->row('NPC', (string) $payload['npc_name']);
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{label: string, value: string}>
     */
    private function group(array $payload): array
    {
        $rows = [];

        foreach (['joined' => 'Joined', 'left' => 'Left'] as $key => $label) {
            $members = $payload[$key] ?? null;

            if (is_array($members) && $members !== []) {
                $rows[] = $this->row($label, implode(', ', array_map('strval', $members)));
            }
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
