<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\StaffMember;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'shop']);
        StaffMember::factory()->for($this->tenant)->create();
    }

    public function test_urls_are_https_behind_the_docker_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.8'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'evil.test'])
            ->get('http://localhost/book')
            ->assertOk()
            ->assertSee('href="https://localhost/book/shop"', false)
            ->assertDontSee('evil.test');
    }

    public function test_forwarded_headers_from_a_public_address_are_ignored(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '8.8.8.8'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->get('http://localhost/book')
            ->assertOk()
            ->assertSee('href="http://localhost/book/shop"', false);
    }
}
