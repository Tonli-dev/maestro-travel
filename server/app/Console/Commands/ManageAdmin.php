<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ManageAdmin extends Command
{
    protected $signature = 'maestro:admin {email} {--name=} {--disable} {--enable} {--reset-password}';

    protected $description = 'Kreiraj racun zaposlenika, promijeni lozinku ili upravljaj pristupom';

    public function handle(): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $validation = Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']]);
        $modeCount = collect(['disable', 'enable', 'reset-password'])->filter(fn (string $option): bool => (bool) $this->option($option))->count();

        if ($validation->fails() || $modeCount > 1) {
            $this->error('Unesite ispravan e-mail i najvise jednu radnju.');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();

        if ($modeCount > 0 && ! $user) {
            $this->error('Racun ne postoji.');

            return self::FAILURE;
        }

        if ($this->option('disable') || $this->option('enable')) {
            DB::transaction(function () use ($user): void {
                $user->forceFill(['is_active' => (bool) $this->option('enable'), 'remember_token' => null])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
            });
            $this->info($this->option('enable') ? 'Pristup ukljucen.' : 'Pristup iskljucen.');

            return self::SUCCESS;
        }

        if ($user && ! $this->option('reset-password')) {
            $this->error('Racun postoji. Koristite --reset-password, --disable ili --enable.');

            return self::FAILURE;
        }

        if ($this->option('no-interaction')) {
            $this->error('Pokrenite interaktivno za siguran unos lozinke.');

            return self::FAILURE;
        }

        $name = $user?->name ?? ($this->option('name') ?: $this->ask('Ime zaposlenika'));
        $password = $this->secret('Lozinka (najmanje 12 znakova)');
        $confirmation = $this->secret('Ponovite lozinku');
        $validation = Validator::make(
            ['name' => $name, 'password' => $password, 'password_confirmation' => $confirmation],
            ['name' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(12)]],
        );

        if ($validation->fails()) {
            $this->error('Ime je obavezno, a lozinke moraju biti jednake i imati najmanje 12 znakova.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $name, $email, $password): void {
            $account = $user ?? new User;
            $account->forceFill([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'is_active' => $user?->is_active ?? true,
                'remember_token' => null,
            ])->save();
            DB::table('sessions')->where('user_id', $account->id)->delete();
        });
        $this->info($user ? 'Lozinka promijenjena.' : 'Racun zaposlenika kreiran.');

        return self::SUCCESS;
    }
}
