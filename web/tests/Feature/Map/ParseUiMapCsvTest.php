<?php

namespace Tests\Feature\Map;

use App\Actions\Map\ParseUiMapCsv;
use App\Exceptions\Map\InvalidUiMapCsvException;
use Tests\TestCase;

class ParseUiMapCsvTest extends TestCase
{
    private ParseUiMapCsv $parser;

    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new ParseUiMapCsv();
    }

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

    public function test_parses_id_and_name_columns()
    {
        $path = $this->tmpCsv("ID;Name_lang\n1;Elwynn Forest\n1418;Badlands\n");

        $rows = $this->parser->exec($path);

        $this->assertSame([
            ['id' => '1', 'name' => 'Elwynn Forest'],
            ['id' => '1418', 'name' => 'Badlands'],
        ], $rows);
    }

    public function test_reads_columns_by_header_regardless_of_order()
    {
        $path = $this->tmpCsv("Name_lang;Flags;ID\nBadlands;0;1418\n");

        $rows = $this->parser->exec($path);

        $this->assertSame([['id' => '1418', 'name' => 'Badlands']], $rows);
    }

    public function test_strips_utf8_bom_from_header()
    {
        $path = $this->tmpCsv("\xEF\xBB\xBFID;Name_lang\n1;Elwynn Forest\n");

        $rows = $this->parser->exec($path);

        $this->assertSame([['id' => '1', 'name' => 'Elwynn Forest']], $rows);
    }

    public function test_throws_when_file_missing()
    {
        $this->expectException(InvalidUiMapCsvException::class);

        $this->parser->exec(sys_get_temp_dir().'/missing-'.uniqid().'.csv');
    }

    public function test_throws_when_empty()
    {
        $this->expectException(InvalidUiMapCsvException::class);

        $this->parser->exec($this->tmpCsv(''));
    }

    public function test_throws_when_required_columns_missing()
    {
        $this->expectException(InvalidUiMapCsvException::class);

        $this->parser->exec($this->tmpCsv("Foo;Bar\n1;2\n"));
    }
}
