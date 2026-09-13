<?php

namespace Tests\Feature\GameSession;

use App\Validators\GameSessionJsonValidator;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GameSessionJsonValidatorTest extends TestCase
{
    public function test_valid_payload_passes_and_returns_validated_data()
    {
        $validated = GameSessionJsonValidator::validate($this->validPayload());

        $this->assertSame(1, $validated['version']);
        $this->assertSame('Thrall', $validated['char']);
        $this->assertCount(1, $validated['points']);
        $this->assertSame(10, $validated['points'][0]['mapId']);
    }

    public function test_are_required_fields_checked_correctly()
    {
        $this->assertFailsValidation([], [
            'sessionId', 'started', 'char', 'realm', 'points',
        ]);
    }

    public function test_version_is_optional_because_savedvariables_has_no_toc_version()
    {
        $payload = $this->validPayload();
        unset($payload['version']);

        $validated = GameSessionJsonValidator::validate($payload);

        $this->assertArrayNotHasKey('version', $validated);
    }

    public function test_coordinates_outside_the_map_are_rejected()
    {
        $this->assertFailsValidation(
            $this->validPayload([], ['x' => 1.5, 'y' => -0.1]),
            ['points.0.x', 'points.0.y'],
        );
    }

    public function test_are_string_fields_checked_correctly()
    {
        $data = $this->validPayload([
            'char' => 123,
            'realm' => ['Azeroth'],
        ]);

        $this->assertFailsValidation($data, ['char', 'realm']);
    }

    public function test_are_numeric_fields_checked_correctly()
    {
        $data = $this->validPayload(
            [
                'version' => 'not-an-int',
                'sessionId' => 'nope',
            ],
            [
                'x' => 'abc',
                'mapId' => 'zzz',
            ]
        );

        $this->assertFailsValidation($data, [
            'version', 'sessionId', 'points.0.x', 'points.0.mapId',
        ]);
    }

    public function test_are_boolean_fields_checked_correctly()
    {
        $data = $this->validPayload([], [
            'inCombat' => 'yes',
            'mounted' => 'nope',
            'onTaxi' => 3,
        ]);

        $this->assertFailsValidation($data, [
            'points.0.inCombat', 'points.0.mounted', 'points.0.onTaxi',
        ]);
    }

    private function validPayload(array $overrides = [], array $pointOverrides = []): array
    {
        return array_merge([
            'version' => 1,
            'sessionId' => 42,
            'started' => 1700000000,
            'char' => 'Thrall',
            'realm' => 'Silvermoon',
            'points' => [
                array_merge(['x' => 0.15, 'y' => 0.25, 'mapId' => 10], $pointOverrides),
            ],
        ], $overrides);
    }

    private function assertFailsValidation(array $data, array $expectedErrorKeys): void
    {
        try {
            GameSessionJsonValidator::validate($data);
            $this->fail('');
        } catch (ValidationException $e) {
            foreach ($expectedErrorKeys as $key) {
                $this->assertArrayHasKey(
                    $key,
                    $e->errors()
                );
            }
        }
    }
}
