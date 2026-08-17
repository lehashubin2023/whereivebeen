<?php

namespace Tests\Feature\GameSession;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportGameSession extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->post(route('game-session.import'), [
            'game_session' => ''
        ]);
        $response->assertStatus(412);
    }
}
