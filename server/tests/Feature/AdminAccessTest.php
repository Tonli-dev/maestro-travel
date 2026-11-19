<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[TestWith(['/admin'])]
    #[TestWith(['/admin/trips'])]
    #[TestWith(['/admin/trips/create'])]
    #[TestWith(['/admin/hotels'])]
    #[TestWith(['/admin/hotels/create'])]
    public function test_guests_are_redirected_to_login(string $url): void
    {
        $this->get($url)->assertRedirect('/admin/login');
    }

    #[TestWith(['/admin'])]
    #[TestWith(['/admin/trips/create'])]
    #[TestWith(['/admin/hotels/create'])]
    public function test_inactive_accounts_cannot_access_admin(string $url): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get($url)->assertForbidden();
    }

    public function test_active_employee_can_log_in(): void
    {
        $user = User::factory()->active()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_disabled_employee_cannot_log_in_with_correct_password(): void
    {
        $user = User::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'password'])
            ->call('authenticate')->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_rejects_wrong_password(): void
    {
        $user = User::factory()->active()->create();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'not-the-password'])
            ->call('authenticate')->assertHasFormErrors(['email']);

        $this->assertGuest();
    }

    public function test_registration_is_not_available(): void
    {
        $this->get('/admin/register')->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_generic_user_creation_cannot_grant_admin_access(): void
    {
        $user = new User;
        $user->fill(['name' => 'Unexpected input', 'email' => 'input@example.test', 'password' => 'safe-password', 'is_active' => true]);
        $user->save();

        $this->assertFalse($user->fresh()->is_active);
    }
}
