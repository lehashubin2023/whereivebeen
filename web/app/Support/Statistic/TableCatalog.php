<?php

namespace App\Support\Statistic;

use App\Enums\GameSession\EventTypeEnum;
use App\Enums\Statistic\StatisticBucketEnum as Bucket;
use App\Enums\Statistic\StatisticCounterEnum as Counter;

class TableCatalog
{
    public const MAX_ROWS = 100;

    /**
     * @return array<int, TableDefinition>
     */
    public function forEvent(EventTypeEnum $type): array
    {
        return match ($type) {
            EventTypeEnum::DEATH => [
                new TableDefinition(
                    bucket: Bucket::DEATH_KILLER,
                    title: 'Killed by',
                    keyColumn: 'Killer',
                    columns: [new TableColumn(Counter::COUNT, 'Deaths')],
                    metaColumn: 'Type',
                ),
                new TableDefinition(
                    bucket: Bucket::DEATH_SPELL,
                    title: 'Killing blows',
                    keyColumn: 'Ability',
                    columns: [new TableColumn(Counter::COUNT, 'Deaths')],
                ),
                new TableDefinition(
                    bucket: Bucket::DEATH_CAUSE,
                    title: 'Environment',
                    keyColumn: 'Cause',
                    columns: [new TableColumn(Counter::COUNT, 'Deaths')],
                ),
            ],
            EventTypeEnum::QUEST => [
                new TableDefinition(
                    bucket: Bucket::QUEST_ACTION,
                    title: 'By action',
                    keyColumn: 'Action',
                    columns: [new TableColumn(Counter::COUNT, 'Times')],
                ),
                new TableDefinition(
                    bucket: Bucket::QUEST_TITLE,
                    title: 'Quests',
                    keyColumn: 'Quest',
                    columns: [
                        new TableColumn(Counter::ACCEPT, 'Accepted'),
                        new TableColumn(Counter::TURNIN, 'Turned in'),
                    ],
                ),
            ],
            EventTypeEnum::LOOT => [
                new TableDefinition(
                    bucket: Bucket::LOOT_ITEM,
                    title: 'Items looted',
                    keyColumn: 'Item',
                    columns: [
                        new TableColumn(Counter::QUANTITY, 'Quantity'),
                        new TableColumn(Counter::DROPS, 'Drops'),
                    ],
                    sortCounter: Counter::QUANTITY,
                ),
            ],
            EventTypeEnum::GATHER => [
                new TableDefinition(
                    bucket: Bucket::GATHER_PROF,
                    title: 'By profession',
                    keyColumn: 'Profession',
                    columns: [new TableColumn(Counter::COUNT, 'Nodes')],
                ),
                new TableDefinition(
                    bucket: Bucket::GATHER_NODE,
                    title: 'Nodes',
                    keyColumn: 'Node',
                    columns: [new TableColumn(Counter::COUNT, 'Times')],
                ),
                new TableDefinition(
                    bucket: Bucket::GATHER_ITEM,
                    title: 'Items gathered',
                    keyColumn: 'Item',
                    columns: [
                        new TableColumn(Counter::QUANTITY, 'Quantity'),
                        new TableColumn(Counter::DROPS, 'Times'),
                    ],
                    sortCounter: Counter::QUANTITY,
                ),
            ],
            EventTypeEnum::VISIT => [
                new TableDefinition(
                    bucket: Bucket::VISIT_PLACE,
                    title: 'By place',
                    keyColumn: 'Place',
                    columns: [new TableColumn(Counter::COUNT, 'Visits')],
                ),
                new TableDefinition(
                    bucket: Bucket::VISIT_NPC,
                    title: 'NPCs',
                    keyColumn: 'NPC',
                    columns: [new TableColumn(Counter::COUNT, 'Visits')],
                ),
            ],
            EventTypeEnum::GROUP => [
                new TableDefinition(
                    bucket: Bucket::GROUP_MEMBER,
                    title: 'Party members',
                    keyColumn: 'Player',
                    columns: [
                        new TableColumn(Counter::JOINED, 'Joined'),
                        new TableColumn(Counter::LEFT, 'Left'),
                    ],
                ),
            ],
            EventTypeEnum::ZONE => [
                new TableDefinition(
                    bucket: Bucket::ZONE_NAME,
                    title: 'Zones',
                    keyColumn: 'Zone',
                    columns: [new TableColumn(Counter::COUNT, 'Entries')],
                ),
                new TableDefinition(
                    bucket: Bucket::ZONE_SUB,
                    title: 'Subzones',
                    keyColumn: 'Subzone',
                    columns: [new TableColumn(Counter::COUNT, 'Entries')],
                ),
            ],
            EventTypeEnum::LEVELUP => [
                new TableDefinition(
                    bucket: Bucket::LEVELUP_LEVEL,
                    title: 'Levels gained',
                    keyColumn: 'Level',
                    columns: [new TableColumn(Counter::COUNT, 'Times')],
                    naturalKeySort: true,
                ),
            ],
            EventTypeEnum::MOUNT => [$this->state(Bucket::MOUNT_STATE)],
            EventTypeEnum::TAXI => [$this->state(Bucket::TAXI_STATE)],
            EventTypeEnum::COMBAT => [$this->state(Bucket::COMBAT_STATE)],
            EventTypeEnum::GAP => [
                new TableDefinition(
                    bucket: Bucket::GAP_REASON,
                    title: 'By reason',
                    keyColumn: 'Reason',
                    columns: [
                        new TableColumn(Counter::COUNT, 'Times'),
                        new TableColumn(Counter::SECONDS, 'Time away', duration: true),
                    ],
                    sortCounter: Counter::COUNT,
                ),
            ],
            default => [],
        };
    }

    public function find(Bucket $bucket): ?TableDefinition
    {
        foreach ($this->forEvent($bucket->eventType()) as $definition) {
            if ($definition->bucket === $bucket) {
                return $definition;
            }
        }

        return null;
    }

    private function state(Bucket $bucket): TableDefinition
    {
        return new TableDefinition(
            bucket: $bucket,
            title: 'By state',
            keyColumn: 'State',
            columns: [new TableColumn(Counter::COUNT, 'Times')],
        );
    }
}
