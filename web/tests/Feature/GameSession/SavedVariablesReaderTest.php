<?php

namespace Tests\Feature\GameSession;

use App\DTOs\GameSession\SavedVariablesSessionDTO;
use App\Exceptions\GameSession\SavedVariablesException;
use App\Exceptions\Lua\LuaSyntaxException;
use App\Support\GameSession\SavedVariables\SavedVariablesReader;
use App\Support\GameSession\SavedVariables\SessionSpool;
use App\Support\Lua\LuaParseLimits;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SavedVariablesReaderTest extends TestCase
{
    private SavedVariablesReader $reader;

    private SessionSpool $spool;

    private string $batch = 'test-batch';

    protected function setUp(): void
    {
        parent::setUp();

        $this->spool = new SessionSpool;
        $this->reader = new SavedVariablesReader($this->spool);
    }

    protected function tearDown(): void
    {
        $this->spool->purge($this->batch);

        parent::tearDown();
    }

    public function test_it_reads_every_session_from_a_real_addon_file(): void
    {
        $sessions = $this->read($this->getFixturesPath('/whereivebeen/WhereIveBeen.lua'));

        $this->assertCount(11, $sessions);

        foreach ($sessions as $session) {
            $this->assertGreaterThan(0, $session->sessionId);
            $this->assertNotNull($session->character());
            $this->assertNotNull($session->realm());
            $this->assertNotNull($session->startedAt());
        }

        $this->assertSame(3514, array_sum(array_map(
            fn (SavedVariablesSessionDTO $session) => $session->pointsCount,
            $sessions,
        )));
    }

    public function test_the_session_id_comes_from_the_table_key(): void
    {
        $sessions = $this->read($this->getFixturesPath('/whereivebeen/WhereIveBeen.lua'));
        $ids = array_map(fn (SavedVariablesSessionDTO $session) => $session->sessionId, $sessions);

        $this->assertContains(1789193069000, $ids);
    }

    public function test_the_header_is_complete_even_when_points_come_first(): void
    {
        $sessions = $this->read($this->fixture(<<<'LUA'
            WhereIveBeenDB = {
            ["sessions"] = {
            [1700000000000] = {
            ["points"] = {
            { ["x"] = 0.5, ["y"] = 0.25, ["mapId"] = 1, ["t"] = 0, },
            },
            ["char"] = "Thrall",
            ["realm"] = "Silvermoon",
            ["started"] = 1700000000,
            },
            },
            }
            LUA));

        $this->assertCount(1, $sessions);
        $this->assertSame('Thrall', $sessions[0]->character());
        $this->assertSame(1, $sessions[0]->pointsCount);
    }

    public function test_points_land_in_the_spool_one_json_object_per_line(): void
    {
        $sessions = $this->read($this->fixture(<<<'LUA'
            WhereIveBeenDB = {
            ["sessions"] = {
            [1700000000000] = {
            ["char"] = "Thrall",
            ["realm"] = "Silvermoon",
            ["started"] = 1700000000,
            ["points"] = {
            { ["x"] = 0.5, ["y"] = 0.25, ["mapId"] = 1, ["t"] = 0, },
            { ["x"] = 0.6, ["y"] = 0.35, ["mapId"] = 1, ["t"] = 15, ["event"] = "mount", ["mounted"] = true, },
            },
            },
            },
            }
            LUA));

        $lines = array_filter(explode("\n", Storage::disk(SessionSpool::DISK)->get($sessions[0]->spoolPath)));

        $this->assertCount(2, $lines);

        $second = json_decode($lines[1], true);

        $this->assertSame('mount', $second['event']);
        $this->assertTrue($second['mounted']);
        $this->assertSame(0.6, $second['x']);
    }

    public function test_a_foreign_addon_file_is_rejected_by_name(): void
    {
        $this->expectException(SavedVariablesException::class);

        try {
            $this->read($this->fixture('SomeOtherAddonDB = { ["sessions"] = {}, }'));
        } catch (SavedVariablesException $e) {
            $this->assertSame('import.lua_global_not_found', $e->errorCode());
            $this->assertSame('SomeOtherAddonDB', $e->errorContext()['found']);

            throw $e;
        }
    }

    public function test_a_damaged_file_reports_the_line(): void
    {
        $this->expectException(LuaSyntaxException::class);

        try {
            $this->read($this->fixture(<<<'LUA'
                WhereIveBeenDB = {
                ["sessions"] = {
                [1700000000000] = {
                ["char"] = "Thrall",
                LUA));
        } catch (LuaSyntaxException $e) {
            $this->assertSame('import.lua_syntax', $e->errorCode());
            $this->assertArrayHasKey('line', $e->errorContext());

            throw $e;
        }
    }

    public function test_a_file_without_sessions_is_rejected(): void
    {
        $this->expectException(SavedVariablesException::class);

        $this->read($this->fixture('WhereIveBeenDB = { ["initialized"] = true, ["sessions"] = {}, }'));
    }

    public function test_comments_indentation_and_trailing_separators_are_tolerated(): void
    {
        $sessions = $this->read($this->fixture(<<<'LUA'
            -- WhereIveBeen SavedVariables
            WhereIveBeenDB = {
            	["initialized"] = true, --[[ inline ]]
            	["sessions"] = {
            		[1700000000000] = {
            			["char"] = "Thrall";
            			["realm"] = "Silvermoon",
            			["started"] = 1700000000,
            			["points"] = {
            				{ ["x"] = 0.5, ["y"] = 0.25, ["t"] = 0 }, -- [1]
            			},
            		},
            	},
            }
            LUA));

        $this->assertCount(1, $sessions);
        $this->assertSame(1, $sessions[0]->pointsCount);
    }

    public function test_windows_non_numbers_are_rejected(): void
    {
        $this->expectException(LuaSyntaxException::class);

        try {
            $this->read($this->fixture(<<<'LUA'
                WhereIveBeenDB = {
                ["sessions"] = {
                [1700000000000] = {
                ["char"] = "Thrall",
                ["tBase"] = -1.#IND,
                },
                },
                }
                LUA));
        } catch (LuaSyntaxException $e) {
            $this->assertSame('import.lua_bad_number', $e->errorCode());

            throw $e;
        }
    }

    public function test_escapes_and_cyrillic_survive_the_round_trip(): void
    {
        $sessions = $this->read($this->fixture(<<<'LUA'
            WhereIveBeenDB = {
            ["sessions"] = {
            [1700000000000] = {
            ["char"] = "Razièl",
            ["realm"] = "Ясеневый лес",
            ["started"] = 1700000000,
            ["points"] = {
            { ["x"] = 0.5, ["y"] = 0.25, ["t"] = 0, ["zone"] = "|cff00ff00Nagrand|r", ["npcName"] = "He said \"hi\"", },
            },
            },
            },
            }
            LUA));

        $this->assertSame('Ясеневый лес', $sessions[0]->realm());

        $point = json_decode(trim(Storage::disk(SessionSpool::DISK)->get($sessions[0]->spoolPath)), true);

        $this->assertSame('|cff00ff00Nagrand|r', $point['zone']);
        $this->assertSame('He said "hi"', $point['npcName']);
    }

    public function test_memory_stays_flat_on_a_large_file(): void
    {
        $path = $this->generate(80000);

        gc_collect_cycles();
        $before = memory_get_usage(true);

        $sessions = $this->read($path);

        $growth = memory_get_usage(true) - $before;

        unlink($path);

        $this->assertSame(80000, $sessions[0]->pointsCount);
        $this->assertLessThan(32 * 1024 * 1024, $growth);
    }

    /**
     * @return array<int, SavedVariablesSessionDTO>
     */
    private function read(string $path): array
    {
        $sessions = [];

        $this->reader->read(
            $path,
            $this->batch,
            new LuaParseLimits,
            function (SavedVariablesSessionDTO $session) use (&$sessions) {
                $sessions[] = $session;
            },
        );

        return $sessions;
    }

    private function fixture(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wivb');
        file_put_contents($path, $contents);

        return $path;
    }

    private function generate(int $points): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wivb');
        $handle = fopen($path, 'wb');

        fwrite($handle, "WhereIveBeenDB = {\n[\"sessions\"] = {\n[1700000000000] = {\n");
        fwrite($handle, "[\"char\"] = \"Thrall\",\n[\"realm\"] = \"Silvermoon\",\n[\"started\"] = 1700000000,\n");
        fwrite($handle, "[\"points\"] = {\n");

        for ($i = 0; $i < $points; $i++) {
            fwrite($handle, sprintf(
                "{\n[\"y\"] = %.5f,\n[\"x\"] = %.5f,\n[\"mapId\"] = 1951,\n[\"t\"] = %.1f,\n},\n",
                ($i % 1000) / 1000,
                (($i * 7) % 1000) / 1000,
                $i * 0.5,
            ));
        }

        fwrite($handle, "},\n},\n},\n}\n");
        fclose($handle);

        return $path;
    }
}
