<?php

namespace Tests\Feature\Map;

use App\Actions\Map\ImportMaps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMapsTest extends TestCase
{
    use RefreshDatabase;

    private ImportMaps $importer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importer = new ImportMaps;
    }

    public function test_inserts_valid_rows()
    {
        $stats = $this->importer->exec([
            ['id' => '1', 'name' => 'Elwynn Forest'],
            ['id' => '1418', 'name' => 'Badlands'],
        ]);

        $this->assertSame(2, $stats['total']);
        $this->assertSame(0, $stats['skipped']);
        $this->assertDatabaseHas('maps', ['id' => 1, 'name' => 'Elwynn Forest']);
        $this->assertDatabaseHas('maps', ['id' => 1418, 'name' => 'Badlands']);
    }

    public function test_skips_rows_with_invalid_id()
    {
        $stats = $this->importer->exec([
            ['name' => 'No Id'],
            ['id' => '0', 'name' => 'Zero'],
            ['id' => '-5', 'name' => 'Negative'],
            ['id' => '70000', 'name' => 'Overflow'],
            ['id' => 'abc', 'name' => 'NaN'],
        ]);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(5, $stats['skipped']);
        $this->assertDatabaseCount('maps', 0);
    }

    public function test_skips_rows_with_invalid_name()
    {
        $stats = $this->importer->exec([
            ['id' => '10'],
            ['id' => '11', 'name' => '   '],
            ['id' => '12', 'name' => 123],
        ]);

        $this->assertSame(0, $stats['total']);
        $this->assertSame(3, $stats['skipped']);
        $this->assertDatabaseCount('maps', 0);
    }

    public function test_trims_and_truncates_name()
    {
        $this->importer->exec([
            ['id' => '5', 'name' => '  Spaced  '],
            ['id' => '6', 'name' => str_repeat('a', 200)],
        ]);

        $this->assertDatabaseHas('maps', ['id' => 5, 'name' => 'Spaced']);
        $this->assertDatabaseHas('maps', ['id' => 6, 'name' => str_repeat('a', 128)]);
    }

    public function test_upsert_updates_existing_name()
    {
        $this->importer->exec([['id' => '7', 'name' => 'Old Name']]);
        $this->importer->exec([['id' => '7', 'name' => 'New Name']]);

        $this->assertDatabaseCount('maps', 1);
        $this->assertDatabaseHas('maps', ['id' => 7, 'name' => 'New Name']);
    }

    public function test_inserts_more_than_one_chunk()
    {
        $rows = [];
        for ($i = 1; $i <= 750; $i++) {
            $rows[] = ['id' => (string) $i, 'name' => "Zone {$i}"];
        }

        $stats = $this->importer->exec($rows);

        $this->assertSame(750, $stats['total']);
        $this->assertDatabaseCount('maps', 750);
    }
}
