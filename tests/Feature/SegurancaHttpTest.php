<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SegurancaHttpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cors.allowed_origins' => ['https://reservas.fac.edu']]);
    }

    private function preflight(string $origem)
    {
        return $this->call('OPTIONS', '/api/espacos', server: [
            'HTTP_ORIGIN'                        => $origem,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
    }

    public function test_cors_libera_so_o_front_configurado(): void
    {
        $this->preflight('https://reservas.fac.edu')
            ->assertHeader('Access-Control-Allow-Origin', 'https://reservas.fac.edu')
            ->assertHeader('Access-Control-Max-Age', '86400');

        $this->assertNotSame(
            'https://site-qualquer.com',
            $this->preflight('https://site-qualquer.com')->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_cabecalhos_de_seguranca(): void
    {
        $this->getJson('/api/espacos')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_so_com_https(): void
    {
        $this->getJson('https://localhost/api/espacos')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
