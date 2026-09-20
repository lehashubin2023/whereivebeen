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

    public function test_has_image_is_true_for_a_shipped_map()
    {
        $this->assertTrue((new Map(['name' => 'Ashenvale']))->hasImage());
    }

    public function test_has_image_is_false_when_the_file_is_missing()
    {
        $this->assertFalse((new Map(['name' => 'Nowhere Land']))->hasImage());
    }
}
