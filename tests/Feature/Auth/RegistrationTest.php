<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_redirects_to_login(): void
    {
        $this->get('/register')->assertRedirect('/login');
    }

    public function test_users_can_be_created_from_the_command_line(): void
    {
        $this->artisan('user:create', [
            'email' => 'test@example.com',
            'password' => 'password',
            '--name' => 'Test User',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'test@example.com', 'name' => 'Test User']);
    }
}
