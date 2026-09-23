<?php

namespace App\Support\GameSession;

class EventPayload
{
    private const FIELDS = [
        'level' => 'level',
        'action' => 'action',
        'title' => 'title',
        'in_combat' => 'inCombat',
        'mounted' => 'mounted',
        'on_taxi' => 'onTaxi',
        'quest_id' => 'questId',
        'items' => 'items',
        'joined' => 'joined',
        'left' => 'left',
        'places' => 'places',
        'node' => 'node',
        'killer' => 'killer',
        'npc_id' => 'npcId',
        'npc_name' => 'npcName',
        'zone' => 'zone',
        'sub_zone' => 'subZone',
        'environment' => 'environment',
        'reason' => 'reason',
        'spell_id' => 'spellId',
        'spell_name' => 'spellName',
        'seconds' => 'seconds',
    ];

    /**
     * @param  array<string, mixed>  $point
     * @return array<string, mixed>
     */
    public static function fromPoint(array $point): array
    {
        $payload = [];

        foreach (self::FIELDS as $payloadKey => $pointKey) {
            if (isset($point[$pointKey])) {
                $payload[$payloadKey] = $point[$pointKey];
            }
        }

        return $payload;
    }
}
