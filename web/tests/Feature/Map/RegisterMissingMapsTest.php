<?php

namespace Tests\Feature\Map;

use App\Actions\Map\RegisterMissingMaps;
use App\Models\Map;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterMissingMapsTest extends TestCase
{
    use RefreshDatabase;

    private RegisterMissingMaps $register;

    protected function setUp(): void
    {
        parent::setUp();

        $this->register = new RegisterMissingMaps;
    }

    public function test_it_creates_a_placeholder_for_an_unknown_map()
    {
        $created = $this->register->exec([2345]);

        $this->assertSame([2345], $created);
        $this->assertDatabaseHas('maps', [
            'id' => 2345,
            'name' => 'Zone 2345',
            'auto_added' => 1,
        ]);
    }

    public function test_it_leaves_an_existing_map_untouched()
    {
        Map::query()->insert([['id' => 331, 'name' => 'Ashenvale']]);

        $created = $this->register->exec([331]);

        $this->assertSame([], $created);
        $this->assertDatabaseHas('maps', [
            'id' => 331,
            'name' => 'Ashenvale',
            'auto_added' => 0,
        ]);
    }

    public function test_it_skips_ids_outside_the_allowed_range()
    {
        $created = $this->register->exec([0, -5, 70000]);

        $this->assertSame([], $created);
        $this->assertDatabaseCount('maps', 0);
    }

    public function test_it_deduplicates_ids()
    {
        $created = $this->register->exec([7, 7, 7]);

        $this->assertSame([7], $created);
        $this->assertDatabaseCount('maps', 1);
    }

    public function test_a_second_call_does_not_duplicate_the_map()
    {
        $this->register->exec([7]);
        $created = $this->register->exec([7]);

        $this->assertSame([], $created);
        $this->assertDatabaseCount('maps', 1);
    }

    public function test_it_creates_only_the_missing_maps()
    {
        Map::query()->insert([['id' => 331, 'name' => 'Ashenvale']]);

        $created = $this->register->exec([331, 2345]);

        $this->assertSame([2345], $created);
        $this->assertDatabaseCount('maps', 2);
    }

    public function test_it_inserts_more_than_one_chunk()
    {
        $created = $this->register->exec(range(1, 750));

        $this->assertCount(750, $created);
        $this->assertDatabaseCount('maps', 750);
    }
}
