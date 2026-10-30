<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SuperAdmin can invite an Admin into a new company.
     */
    public function test_super_admin_can_invite_admin_to_new_company(): void
    {
        Mail::fake();

        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => null,
        ]);

        $response = $this->actingAs($superAdmin)->post(
            '/companies/invite-admin',
            [
                'company_name' => 'Test Company',
                'email' => 'admin@example.com',
            ]
        );

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('companies', [
            'name' => 'Test Company',
        ]);

        $invitation = Invitation::where(
            'email',
            'admin@example.com'
        )->first();

        $this->assertNotNull($invitation);
        $this->assertSame('admin', $invitation->role);
        $this->assertNotEquals(
            'admin@example.com',
            $invitation->token_hash
        );
    }

    /**
     * Admin can invite a Member into their own company.
     */
    public function test_admin_can_invite_member_to_own_company(): void
    {
        Mail::fake();

        $company = Company::create([
            'name' => 'Test Company',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $response = $this->actingAs($admin)->post(
            '/company/invitations',
            [
                'email' => 'member@example.com',
                'role' => 'member',
            ]
        );

        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('invitations', [
            'company_id' => $company->id,
            'email' => 'member@example.com',
            'role' => 'member',
            'invited_by' => $admin->id,
        ]);
    }

    /**
     * Invitation tokens are stored as SHA-256 hashes.
     */
    public function test_invitation_token_is_stored_as_hash(): void
    {
        Mail::fake();

        $company = Company::create([
            'name' => 'Token Test Company',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $this->actingAs($admin)->post(
            '/company/invitations',
            [
                'email' => 'token@example.com',
                'role' => 'member',
            ]
        );

        $invitation = Invitation::where(
            'email',
            'token@example.com'
        )->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $invitation->token_hash
        );
    }

    /**
     * Invalid invitation tokens must return 404.
     */
    public function test_invalid_invitation_token_returns_404(): void
    {
        $response = $this->get(
            '/invitations/invalid-token'
        );

        $response->assertNotFound();
    }

    /**
     * Expired invitations cannot be accepted.
     */
    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $company = Company::create([
            'name' => 'Expired Company',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $token = str_repeat('a', 64);

        Invitation::create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'expired@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->subHour(),
        ]);

        $response = $this->get(
            "/invitations/{$token}"
        );

        $response->assertNotFound();
    }

    /**
     * Successfully accepting an invitation creates the user.
     */
    public function test_user_can_accept_valid_invitation(): void
    {
        $company = Company::create([
            'name' => 'Acceptance Company',
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'company_id' => $company->id,
        ]);

        $token = str_repeat('b', 64);

        $invitation = Invitation::create([
            'company_id' => $company->id,
            'invited_by' => $admin->id,
            'email' => 'newmember@example.com',
            'role' => 'member',
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(48),
        ]);

        $response = $this->post(
            "/invitations/{$token}",
            [
                'name' => 'New Member',
                'password' => 'StrongPassword123!',
                'password_confirmation' => 'StrongPassword123!',
            ]
        );

        $response->assertRedirect('/login');

        $this->assertDatabaseHas('users', [
            'name' => 'New Member',
            'email' => 'newmember@example.com',
            'company_id' => $company->id,
            'role' => 'member',
        ]);

        $this->assertNotNull(
            $invitation->fresh()->accepted_at
        );
    }
}
