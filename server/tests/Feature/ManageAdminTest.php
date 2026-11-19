<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManageAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_an_active_employee_with_a_hashed_password(): void
    {
        $this->artisan('maestro:admin', ['email' => 'STAFF@example.test', '--name' => 'Zaposlenik'])
            ->expectsQuestion('Lozinka (najmanje 12 znakova)', 'a-secure-test-password')
            ->expectsQuestion('Ponovite lozinku', 'a-secure-test-password')->assertSuccessful();

        $user = User::where('email', 'staff@example.test')->sole();
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('a-secure-test-password', $user->password));
    }

    public function test_short_password_creates_no_account(): void
    {
        $this->artisan('maestro:admin', ['email' => 'staff@example.test', '--name' => 'Zaposlenik'])
            ->expectsQuestion('Lozinka (najmanje 12 znakova)', 'short')
            ->expectsQuestion('Ponovite lozinku', 'short')->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_disable_revokes_account_and_database_sessions(): void
    {
        $user = User::factory()->active()->create();
        DB::table('sessions')->insert(['id' => 'session-to-revoke', 'user_id' => $user->id, 'payload' => 'test', 'last_activity' => time()]);

        $this->artisan('maestro:admin', ['email' => $user->email, '--disable' => true])->assertSuccessful();

        $this->assertFalse($user->fresh()->is_active);
        $this->assertNull($user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_existing_account_is_not_overwritten_without_an_explicit_action(): void
    {
        $user = User::factory()->active()->create();
        $passwordHash = $user->password;

        $this->artisan('maestro:admin', ['email' => $user->email])->assertFailed();

        $this->assertSame($passwordHash, $user->fresh()->password);
    }

    public function test_password_reset_does_not_reactivate_a_disabled_account(): void
    {
        $user = User::factory()->create();

        $this->artisan('maestro:admin', ['email' => $user->email, '--reset-password' => true])
            ->expectsQuestion('Lozinka (najmanje 12 znakova)', 'changed-test-password')
            ->expectsQuestion('Ponovite lozinku', 'changed-test-password')->assertSuccessful();

        $this->assertTrue(Hash::check('changed-test-password', $user->fresh()->password));
        $this->assertFalse($user->fresh()->is_active);
    }
}
