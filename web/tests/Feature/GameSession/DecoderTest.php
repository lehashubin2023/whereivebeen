<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\DecodeRawInput;
use App\Exceptions\GameSession\InvalidGameSessionInputException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecoderTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_decoder_reject_empty_input()
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Empty input');
        app()->make(DecodeRawInput::class)->exec('   ');
    }

    public function test_is_decoder_decode_json_incorrect()
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid JSON input');
        app()->make(DecodeRawInput::class)->exec('{"broken":');
    }

    public function test_is_decoder_decode_deep_json_incorrect()
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid JSON input');
        app()->make(DecodeRawInput::class)->exec(json_encode([
            'sessionId' => 1234123,
            'points' => [
                [
                    'items' => [
                        [
                            'id' => 1,
                            'nested' => [
                                'tooDeep' => [],
                            ],
                        ],
                    ],
                ],
            ],
        ]));
    }

    public function test_is_decoder_decode_correct()
    {
        $firstVal = [
            123456 => 'someVal',
        ];
        $decodedArray1 = app()->make(DecodeRawInput::class)->exec(json_encode($firstVal));

        $secondVal = [
            123456 => [
                'someVal' => true,
            ],
        ];
        $decodedArray2 = app()->make(DecodeRawInput::class)->exec(json_encode($secondVal));

        $this->assertEquals($firstVal, $decodedArray1);
        $this->assertEquals($secondVal, $decodedArray2);
    }
}
