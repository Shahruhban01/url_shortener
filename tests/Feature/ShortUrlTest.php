<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ShortUrl;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortUrlTest extends TestCase
{
    use RefreshDatabase;

    private function createCompany(string $name): Company
    {
        return Company::create(['name' => $name]);
    }

    private function createUser(
        string $role,
        ?int $companyId = null
    ): User {
        return User::factory()->create([
            'role' => $role,
            'company_id' => $companyId,
        ]);
    }

    private function createShortUrl(
        User $user,
        string $code,
        string $originalUrl
    ): ShortUrl {
        $shortUrl = new ShortUrl([
            'code' => $code,
            'original_url' => $originalUrl,
        ]);

        $shortUrl->company()->associate($user->company);
        $shortUrl->user()->associate($user);
        $shortUrl->save();

        return $shortUrl;
    }

    public function test_admin_can_create_a_short_url(): void
    {
        $company = $this->createCompany('Company A');
        $admin = $this->createUser('admin', $company->id);

        $response = $this->actingAs($admin)->post('/short-urls', [
            'original_url' => 'https://example.com/admin',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('short_urls', [
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'original_url' => 'https://example.com/admin',
        ]);
    }

    public function test_member_can_create_a_short_url(): void
    {
        $company = $this->createCompany('Company A');
        $member = $this->createUser('member', $company->id);

        $response = $this->actingAs($member)->post('/short-urls', [
            'original_url' => 'https://example.com/member',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('short_urls', [
            'company_id' => $company->id,
            'user_id' => $member->id,
            'original_url' => 'https://example.com/member',
        ]);
    }

    public function test_super_admin_cannot_create_a_short_url(): void
    {
        $superAdmin = $this->createUser('super_admin');

        $response = $this->actingAs($superAdmin)->post('/short-urls', [
            'original_url' => 'https://example.com/superadmin',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('short_urls', [
            'original_url' => 'https://example.com/superadmin',
        ]);
    }

    public function test_admin_sees_only_urls_from_their_company(): void
    {
        $companyA = $this->createCompany('Company A');
        $companyB = $this->createCompany('Company B');

        $adminA = $this->createUser('admin', $companyA->id);
        $memberA = $this->createUser('member', $companyA->id);
        $memberB = $this->createUser('member', $companyB->id);

        $this->createShortUrl(
            $memberA,
            'companyA1',
            'https://example.com/company-a'
        );

        $this->createShortUrl(
            $memberB,
            'companyB1',
            'https://example.com/company-b'
        );

        $response = $this->actingAs($adminA)->get('/short-urls');

        $response->assertOk();
        $response->assertSee('https://example.com/company-a');
        $response->assertDontSee('https://example.com/company-b');
    }

    public function test_member_sees_only_urls_they_created(): void
    {
        $company = $this->createCompany('Company A');

        $memberA = $this->createUser('member', $company->id);
        $memberB = $this->createUser('member', $company->id);

        $this->createShortUrl(
            $memberA,
            'memberA01',
            'https://example.com/member-a'
        );

        $this->createShortUrl(
            $memberB,
            'memberB01',
            'https://example.com/member-b'
        );

        $response = $this->actingAs($memberA)->get('/short-urls');

        $response->assertOk();
        $response->assertSee('https://example.com/member-a');
        $response->assertDontSee('https://example.com/member-b');
    }

    public function test_public_short_url_redirects_to_original_url(): void
    {
        $company = $this->createCompany('Company A');
        $member = $this->createUser('member', $company->id);

        $this->createShortUrl(
            $member,
            'testCode',
            'https://example.com/destination'
        );

        $response = $this->get('/testCode');

        $response->assertRedirect('https://example.com/destination');

        $this->assertDatabaseHas('short_urls', [
            'code' => 'testCode',
            'clicks' => 1,
        ]);
    }
}
