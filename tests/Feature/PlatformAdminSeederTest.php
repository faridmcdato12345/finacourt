<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\PlatformAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class PlatformAdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_verified_platform_administrator_from_configuration(): void
    {
        config()->set('platform.administrator', [
            'name' => 'FinACourt Platform Owner',
            'email' => 'platform-owner@example.com',
            'password' => 'a-strong-bootstrap-password',
        ]);

        $this->seed(PlatformAdminSeeder::class);

        $administrator = User::query()->where('email', 'platform-owner@example.com')->firstOrFail();

        $this->assertSame('FinACourt Platform Owner', $administrator->name);
        $this->assertTrue($administrator->is_platform_admin);
        $this->assertNotNull($administrator->email_verified_at);
        $this->assertTrue(Hash::check('a-strong-bootstrap-password', $administrator->password));
        $this->assertFalse($administrator->memberships()->exists());

        $this->post(route('login'), [
            'email' => 'platform-owner@example.com',
            'password' => 'a-strong-bootstrap-password',
        ])->assertRedirect(route('platform.dashboard'));

        $this->assertAuthenticatedAs($administrator);
    }

    public function test_rerunning_it_promotes_an_existing_user_without_resetting_their_password(): void
    {
        $existing = User::factory()->unverified()->create([
            'name' => 'Existing Account',
            'email' => 'platform-owner@example.com',
            'password' => 'existing-private-password',
            'is_platform_admin' => false,
        ]);

        config()->set('platform.administrator', [
            'name' => 'Ignored Replacement Name',
            'email' => 'platform-owner@example.com',
            'password' => 'different-bootstrap-password',
        ]);

        $this->seed(PlatformAdminSeeder::class);

        $existing->refresh();

        $this->assertSame('Existing Account', $existing->name);
        $this->assertTrue($existing->is_platform_admin);
        $this->assertNotNull($existing->email_verified_at);
        $this->assertTrue(Hash::check('existing-private-password', $existing->password));
        $this->assertSame(1, User::query()->where('email', 'platform-owner@example.com')->count());
    }

    public function test_it_refuses_to_create_an_administrator_without_a_valid_email(): void
    {
        config()->set('platform.administrator', [
            'name' => 'Platform Owner',
            'email' => 'not-an-email',
            'password' => 'a-strong-bootstrap-password',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PLATFORM_ADMIN_EMAIL must contain a valid email address.');

        $this->seed(PlatformAdminSeeder::class);
    }
}
