<?php

namespace Tests\Feature\Map;

use App\Models\Map;
use Tests\TestCase;

class MapImagePathTest extends TestCase
{
    public function test_builds_path_from_name()
    {
        $map = new Map(['name' => 'Elwynn Forest']);

        $this->assertSame('/maps/Elwynn_Forest.png', $map->image_path);
    }

    public function test_strips_punctuation_from_name()
    {
        $map = new Map(['name' => "Un'Goro Crater"]);

        $this->assertSame('/maps/UnGoro_Crater.png', $map->image_path);
    }

    public function test_image_path_is_appended_to_array()
    {
        $map = new Map(['name' => 'Badlands']);

        $this->assertSame('/maps/Badlands.png', $map->toArray()['image_path']);
    }
}
