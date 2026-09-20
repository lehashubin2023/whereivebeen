<?php

namespace Tests\Feature\GameSession;

use App\Support\GameSession\MapId;
use Tests\TestCase;

class MapIdTest extends TestCase
{
    public function test_it_nulls_empty_and_out_of_range_values()
    {
        $this->assertNull(MapId::sanitize(null));
        $this->assertNull(MapId::sanitize(''));
        $this->assertNull(MapId::sanitize(0));
        $this->assertNull(MapId::sanitize(-1));
        $this->assertNull(MapId::sanitize(65536));
        $this->assertNull(MapId::sanitize('zzz'));
    }

    public function test_it_casts_numeric_input_to_int()
    {
        $this->assertSame(331, MapId::sanitize('331'));
        $this->assertSame(331, MapId::sanitize(331.0));
        $this->assertSame(65535, MapId::sanitize(65535));
    }

    public function test_it_collects_distinct_ids_from_points()
    {
        $ids = MapId::distinctFromPoints([
            ['mapId' => 331],
            ['mapId' => '331'],
            ['x' => 0.1, 'y' => 0.2],
            ['mapId' => null],
            ['mapId' => 70000],
            ['mapId' => 47],
        ]);

        $this->assertSame([331, 47], $ids);
    }

    public function test_it_returns_an_empty_list_when_no_point_has_a_map()
    {
        $this->assertSame([], MapId::distinctFromPoints([['x' => 0.1, 'y' => 0.2]]));
    }
}
