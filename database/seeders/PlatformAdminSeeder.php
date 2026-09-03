<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

class PlatformAdminSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $name = trim((string) config('platform.administrator.name', 'Platform Owner'));
        $email = Str::lower(trim((string) config('platform.administrator.email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('PLATFORM_ADMIN_EMAIL must contain a valid email address.');
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $password = (string) config('platform.administrator.password');

            if (trim($password) === '' || strlen($password) < 12) {
                throw new RuntimeException('PLATFORM_ADMIN_PASSWORD must contain at least 12 characters when creating the account.');
            }

            $user = User::query()->create([
                'name' => $name !== '' ? $name : 'Platform Owner',
                'email' => $email,
                'email_verified_at' => now(),
                'password' => $password,
                'is_platform_admin' => true,
            ]);

            $this->command?->info("Platform administrator [{$user->email}] created.");

            return;
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'is_platform_admin' => true,
        ])->save();

        $this->command?->info("Existing user [{$user->email}] granted platform administrator access. The existing password was preserved.");
    }
}
