<?php

namespace Tests\Feature\Map;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportMapsCommandTest extends TestCase
{
    use RefreshDatabase;

    private array $tmpFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    private function tmpCsv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'uimap');
        file_put_contents($path, $contents);
        $this->tmpFiles[] = $path;

        return $path;
    }

    public function test_imports_maps_from_csv_source()
    {
        $path = $this->tmpCsv("ID;Name_lang\n1;Elwynn Forest\n1418;Badlands\n");

        $this->artisan('maps:import', ['--source' => $path])
            ->expectsOutputToContain('Imported maps: 2')
            ->assertSuccessful();

        $this->assertDatabaseHas('maps', ['id' => 1, 'name' => 'Elwynn Forest']);
        $this->assertDatabaseHas('maps', ['id' => 1418, 'name' => 'Badlands']);
    }

    public function test_fails_on_invalid_csv()
    {
        $path = $this->tmpCsv("Foo;Bar\n1;2\n");

        $this->artisan('maps:import', ['--source' => $path])
            ->assertFailed();

        $this->assertDatabaseCount('maps', 0);
    }
}
