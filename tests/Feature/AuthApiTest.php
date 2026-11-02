<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'John Doe',
            'email' => 'john@marinaaqua.com',
            'username' => 'johndoe',
            'password' => Hash::make('Secret1234!'),
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_user_can_login_with_email(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'john@marinaaqua.com',
            'password' => 'Secret1234!',
            'device_name' => 'TestDevice',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'username',
                        'role',
                        'is_active',
                    ],
                ],
            ]);
    }

    public function test_user_can_login_with_username(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'johndoe',
            'password' => 'Secret1234!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.user.username', 'johndoe');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'john@marinaaqua.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('status', 'error');
    }

    public function test_login_fails_for_inactive_user(): void
    {
        $this->user->update(['is_active' => false]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => 'john@marinaaqua.com',
            'password' => 'Secret1234!',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertSee('deactivated');
    }

    public function test_login_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['login', 'password']);
    }

    public function test_authenticated_user_can_retrieve_profile(): void
    {
        $token = $this->user->createToken('Test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.email', 'john@marinaaqua.com');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401);
    }

    public function test_user_can_logout_and_revoke_token(): void
    {
        $token = $this->user->createToken('Test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // Confirm token is revoked
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_user_can_change_password(): void
    {
        $token = $this->user->createToken('Test')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'Secret1234!',
                'new_password' => 'NewSecret5678!',
                'new_password_confirmation' => 'NewSecret5678!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecret5678!', $this->user->password));
    }
}
