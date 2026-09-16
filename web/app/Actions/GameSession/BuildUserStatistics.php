<?php

namespace App\Actions\GameSession;

use App\DTOs\GameSession\StatisticGroupDTO;
use App\DTOs\GameSession\StatisticTableDTO;
use App\Enums\GameSession\EventTypeEnum;
use App\Models\Event;
use App\Models\GameSession;
use App\Models\User;
use App\Models\WayPoint;

class BuildUserStatistics
{
    private const MAX_ROWS = 100;

    private const PLACES = [
        'merchant' => 'Merchant',
        'repair' => 'Repair',
        'bank' => 'Bank',
        'guildbank' => 'Guild bank',
        'auction' => 'Auction house',
        'mail' => 'Mailbox',
        'trainer' => 'Trainer',
        'flightmaster' => 'Flight master',
        'stable' => 'Stable',
        'barber' => 'Barber',
        'trade' => 'Trade',
    ];

    /** @var array<int, int> */
    private array $totals = [];

    /** @var array<string, array<string, array<string, int>>> */
    private array $counters = [];

    /** @var array<string, array<string, array<string, string>>> */
    private array $notes = [];

    public function __construct(private readonly MeasureSessionTime $measureTime) {}

    /**
     * @return array<string, mixed>
     */
    public function exec(User $user): array
    {
        $this->totals = [];
        $this->counters = [];
        $this->notes = [];

        /** @var array<int, int> $sessionIds */
        $sessionIds = GameSession::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();

        $this->collect($sessionIds);

        return [
            'overview' => $this->overview($sessionIds),
            'groups' => $this->groups(),
        ];
    }

    /**
     * @param  array<int, int>  $sessionIds
     */
    private function collect(array $sessionIds): void
    {
        if ($sessionIds === []) {
            return;
        }

        Event::query()
            ->whereIn('game_session_id', $sessionIds)
            ->orderBy('game_session_id')
            ->orderBy('sequence')
            ->select(['event_type_id', 'payload'])
            ->cursor()
            ->each(function (Event $event): void {
                $type = EventTypeEnum::tryFrom((int) $event->getAttribute('event_type_id'));

                if ($type === null) {
                    return;
                }

                $this->totals[$type->value] = ($this->totals[$type->value] ?? 0) + 1;

                $this->ingest($type, (array) $event->getAttribute('payload'));
            });
    }

    /**
     * @param  array<int, int>  $sessionIds
     * @return array<int, array{label: string, value: string}>
     */
    private function overview(array $sessionIds): array
    {
        $points = 0;
        $maps = 0;

        if ($sessionIds !== []) {
            $points = WayPoint::query()
                ->whereIn('game_session_id', $sessionIds)
                ->count();

            $maps = WayPoint::query()
                ->whereIn('game_session_id', $sessionIds)
                ->whereNotNull('map_id')
                ->distinct()
                ->count('map_id');
        }

        return [
            ['label' => __('Sessions'), 'value' => (string) count($sessionIds)],
            ['label' => __('Waypoints'), 'value' => (string) $points],
            ['label' => __('Zones visited'), 'value' => (string) $maps],
            ['label' => __('Events'), 'value' => (string) array_sum($this->totals)],
            ['label' => __('Time played'), 'value' => self::duration(array_sum($this->measureTime->exec($sessionIds)))],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function groups(): array
    {
        $groups = [];

        foreach (EventTypeEnum::cases() as $type) {
            $total = $this->totals[$type->value] ?? 0;

            if ($total === 0) {
                continue;
            }

            $groups[] = StatisticGroupDTO::fromArray([
                'slug' => $type->slug(),
                'label' => $type->label(),
                'total' => $total,
                'tables' => $this->tablesFor($type),
            ])->toArray();
        }

        usort($groups, fn (array $first, array $second) => (int) $second['total'] <=> (int) $first['total']);

        return $groups;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tablesFor(EventTypeEnum $type): array
    {
        $tables = match ($type) {
            EventTypeEnum::DEATH => [
                $this->table('Killed by', 'Killer', 'death.killer', ['count' => 'Deaths'], ['kind' => 'Type']),
                $this->table('Killing blows', 'Ability', 'death.spell', ['count' => 'Deaths']),
                $this->table('Environment', 'Cause', 'death.cause', ['count' => 'Deaths']),
            ],
            EventTypeEnum::QUEST => [
                $this->table('By action', 'Action', 'quest.action', ['count' => 'Times']),
                $this->table('Quests', 'Quest', 'quest.title', ['accept' => 'Accepted', 'turnin' => 'Turned in']),
            ],
            EventTypeEnum::LOOT => [
                $this->table('Items looted', 'Item', 'loot.item', ['quantity' => 'Quantity', 'drops' => 'Drops'], [], 'quantity'),
            ],
            EventTypeEnum::GATHER => [
                $this->table('By profession', 'Profession', 'gather.prof', ['count' => 'Nodes']),
                $this->table('Nodes', 'Node', 'gather.node', ['count' => 'Times']),
                $this->table('Items gathered', 'Item', 'gather.item', ['quantity' => 'Quantity', 'drops' => 'Times'], [], 'quantity'),
            ],
            EventTypeEnum::VISIT => [
                $this->table('By place', 'Place', 'visit.place', ['count' => 'Visits']),
                $this->table('NPCs', 'NPC', 'visit.npc', ['count' => 'Visits']),
            ],
            EventTypeEnum::GROUP => [
                $this->table('Party members', 'Player', 'group.member', ['joined' => 'Joined', 'left' => 'Left']),
            ],
            EventTypeEnum::ZONE => [
                $this->table('Zones', 'Zone', 'zone.name', ['count' => 'Entries']),
                $this->table('Subzones', 'Subzone', 'zone.sub', ['count' => 'Entries']),
            ],
            EventTypeEnum::LEVELUP => [
                $this->table('Levels gained', 'Level', 'levelup.level', ['count' => 'Times'], [], 'key'),
            ],
            EventTypeEnum::MOUNT => [
                $this->table('By state', 'State', 'mount.state', ['count' => 'Times']),
            ],
            EventTypeEnum::TAXI => [
                $this->table('By state', 'State', 'taxi.state', ['count' => 'Times']),
            ],
            EventTypeEnum::COMBAT => [
                $this->table('By state', 'State', 'combat.state', ['count' => 'Times']),
            ],
            EventTypeEnum::GAP => [
                $this->table('By reason', 'Reason', 'gap.reason', ['count' => 'Times', 'seconds' => 'Time away'], [], 'count', ['seconds']),
            ],
            default => [],
        };

        return array_values(array_filter($tables));
    }

    /**
     * @param  array<string, string>  $counters  ключ счётчика => заголовок колонки
     * @param  array<string, string>  $notes  ключ пометки => заголовок колонки
     * @param  array<int, string>  $durations  счётчики, которые показываются как длительность
     * @return array<string, mixed>|null
     */
    private function table(
        string $title,
        string $keyColumn,
        string $bucket,
        array $counters,
        array $notes = [],
        ?string $sortBy = null,
        array $durations = [],
    ): ?array {
        $source = $this->counters[$bucket] ?? [];

        if ($source === []) {
            return null;
        }

        $keys = array_map('strval', array_keys($source));

        usort($keys, function (string $first, string $second) use ($source, $sortBy): int {
            if ($sortBy === 'key') {
                return strnatcasecmp($first, $second);
            }

            $weight = fn (string $key): int => $sortBy === null
                ? array_sum($source[$key])
                : ($source[$key][$sortBy] ?? 0);

            return $weight($second) <=> $weight($first) ?: strnatcasecmp($first, $second);
        });

        $rows = [];

        foreach (array_slice($keys, 0, self::MAX_ROWS) as $key) {
            $row = [$key];

            foreach (array_keys($notes) as $note) {
                $row[] = $this->notes[$bucket][$key][$note] ?? '—';
            }

            foreach (array_keys($counters) as $counter) {
                $value = $source[$key][$counter] ?? 0;

                $row[] = in_array($counter, $durations, true)
                    ? self::duration($value)
                    : (string) $value;
            }

            $rows[] = $row;
        }

        return StatisticTableDTO::fromArray([
            'title' => __($title),
            'columns' => array_merge(
                [__($keyColumn)],
                array_map(self::translate(...), array_values($notes)),
                array_map(self::translate(...), array_values($counters)),
            ),
            'rows' => $rows,
            'rows_total' => count($keys),
        ])->toArray();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ingest(EventTypeEnum $type, array $payload): void
    {
        match ($type) {
            EventTypeEnum::DEATH => $this->death($payload),
            EventTypeEnum::QUEST => $this->quest($payload),
            EventTypeEnum::LOOT => $this->items($payload, 'loot.item'),
            EventTypeEnum::GATHER => $this->gather($payload),
            EventTypeEnum::VISIT => $this->visit($payload),
            EventTypeEnum::GROUP => $this->group($payload),
            EventTypeEnum::ZONE => $this->zone($payload),
            EventTypeEnum::LEVELUP => $this->levelUp($payload),
            EventTypeEnum::MOUNT => $this->flag('mount.state', $payload, 'mounted', 'Mounted', 'Dismounted'),
            EventTypeEnum::TAXI => $this->flag('taxi.state', $payload, 'on_taxi', 'Takeoff', 'Landing'),
            EventTypeEnum::COMBAT => $this->flag('combat.state', $payload, 'in_combat', 'Entered combat', 'Left combat'),
            EventTypeEnum::GAP => $this->gap($payload),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function death(array $payload): void
    {
        if (isset($payload['environment'])) {
            $this->tally('death.cause', ucfirst(strtolower((string) $payload['environment'])));
        }

        $killer = (array) ($payload['killer'] ?? []);
        $name = (string) ($killer['name'] ?? '');

        if ($name !== '') {
            $this->tally('death.killer', $name);
            $this->note('death.killer', $name, 'kind', $this->killerKind($killer));
        }

        if (isset($killer['spell'])) {
            $this->tally('death.spell', (string) $killer['spell']);
        }
    }

    /**
     * @param  array<string, mixed>  $killer
     */
    private function killerKind(array $killer): string
    {
        $pvp = (bool) ($killer['pvp'] ?? false);

        if (isset($killer['class'])) {
            return ucfirst(strtolower((string) $killer['class'])).($pvp ? ' (player)' : '');
        }

        if (isset($killer['creatureType'])) {
            return (string) $killer['creatureType'];
        }

        return $pvp ? __('Player') : '—';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function quest(array $payload): void
    {
        $counter = ($payload['action'] ?? null) === 'turnin' ? 'turnin' : 'accept';

        $this->tally('quest.action', $counter === 'turnin' ? __('Turned in') : __('Accepted'));

        $title = (string) ($payload['title'] ?? '');

        if ($title === '' && isset($payload['quest_id'])) {
            $title = '#'.$payload['quest_id'];
        }

        $this->tally('quest.title', $title, $counter);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gather(array $payload): void
    {
        $node = (array) ($payload['node'] ?? []);

        if (isset($node['name'])) {
            $this->tally('gather.node', (string) $node['name']);
        }

        if (isset($node['prof'])) {
            $this->tally('gather.prof', ucfirst((string) $node['prof']));
        }

        $this->items($payload, 'gather.item');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function items(array $payload, string $bucket): void
    {
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $name = (string) ($item['name'] ?? $item['id'] ?? '');

            $this->tally($bucket, $name, 'quantity', max(1, (int) ($item['n'] ?? 1)));
            $this->tally($bucket, $name, 'drops');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function visit(array $payload): void
    {
        $places = $payload['places'] ?? null;

        if (is_array($places)) {
            foreach ($places as $place) {
                $slug = (string) $place;

                $this->tally('visit.place', __(self::PLACES[$slug] ?? ucfirst($slug)));
            }
        }

        if (isset($payload['npc_name'])) {
            $this->tally('visit.npc', (string) $payload['npc_name']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function group(array $payload): void
    {
        foreach (['joined' => 'joined', 'left' => 'left'] as $key => $counter) {
            $members = $payload[$key] ?? null;

            if (! is_array($members)) {
                continue;
            }

            foreach ($members as $member) {
                $this->tally('group.member', (string) $member, $counter);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function zone(array $payload): void
    {
        if (isset($payload['zone'])) {
            $this->tally('zone.name', (string) $payload['zone']);
        }

        if (isset($payload['sub_zone'])) {
            $this->tally('zone.sub', (string) $payload['sub_zone']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function levelUp(array $payload): void
    {
        if (isset($payload['level'])) {
            $this->tally('levelup.level', (string) $payload['level']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gap(array $payload): void
    {
        $reason = ucfirst((string) ($payload['reason'] ?? 'unknown'));

        $this->tally('gap.reason', __($reason));
        $this->tally('gap.reason', __($reason), 'seconds', max(0, (int) ($payload['seconds'] ?? 0)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function flag(string $bucket, array $payload, string $key, string $onLabel, string $offLabel): void
    {
        $this->tally($bucket, ($payload[$key] ?? false) ? __($onLabel) : __($offLabel));
    }

    private function tally(string $bucket, string $key, string $counter = 'count', int $by = 1): void
    {
        if ($key === '') {
            return;
        }

        $this->counters[$bucket][$key][$counter] = ($this->counters[$bucket][$key][$counter] ?? 0) + $by;
    }

    private function note(string $bucket, string $key, string $field, string $value): void
    {
        $this->notes[$bucket][$key][$field] ??= $value;
    }

    private static function duration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.self::translate('s');
        }

        $minutes = (int) round($seconds / 60);

        if ($minutes < 60) {
            return $minutes.self::translate('m');
        }

        return intdiv($minutes, 60).self::translate('h').' '.($minutes % 60).self::translate('m');
    }

    /**
     * @param  array<string, int|string>  $replace
     */
    private static function translate(string $key, array $replace = []): string
    {
        return (string) __($key, $replace);
    }
}
