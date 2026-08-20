<?php

namespace Tests\Feature\GameSession;

use App\Actions\GameSession\DecodeRawInput;
use App\Exceptions\GameSession\InvalidGameSessionInputException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecoderTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_decoder_decode_base64_incorrect()
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid base64 input');
        app()->make(DecodeRawInput::class)->exec('SGV%sbG9_b3JsZA==');
    }

    public function test_is_decoder_decode_json_incorrect()
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid JSON input');
        app()->make(DecodeRawInput::class)->exec(base64_encode('{"broken":'));
    }

    public function test_is_decoder_decode_deep_json_incorrect()
    {
        $this->expectException(InvalidGameSessionInputException::class);
        $this->expectExceptionMessage('Invalid JSON input');
        app()->make(DecodeRawInput::class)->exec(base64_encode(json_encode([
            1234123 => [
                'points' => [
                    'someVal' => false,
                    'someVal2' => []
                ]
            ]
        ])));
    }

    public function test_is_decoder_decode_correct()
    {
        $firstVal = [
            123456 => 'someVal'
        ];
        $decodedArray1 = app()->make(DecodeRawInput::class)->exec(base64_encode(json_encode($firstVal)));

        $secondVal = [
            123456 => [
                'someVal' => true
            ]
        ];
        $decodedArray2 = app()->make(DecodeRawInput::class)->exec(base64_encode(json_encode($secondVal)));

        $this->assertEquals($firstVal, $decodedArray1);
        $this->assertEquals($secondVal, $decodedArray2);
    }
}
