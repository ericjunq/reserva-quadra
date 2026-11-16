<?php

namespace Tests;

use App\Mail\CodigoLoginMail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);
        $this->travelTo(Carbon::parse('2026-11-09 10:00'));
    }

    protected function comoAdmin(): User
    {
        $admin = User::factory()->create();
        Sanctum::actingAs($admin);

        return $admin;
    }

    protected function entrar(string $email, string $senha): TestResponse
    {
        if (! Mail::getFacadeRoot() instanceof MailFake) {
            Mail::fake();
        }

        $resposta = $this->postJson('/api/login', ['email' => $email, 'password' => $senha]);

        if (! $resposta->json('desafio')) {
            return $resposta;
        }

        return $this->postJson('/api/login/codigo', [
            'desafio' => $resposta->json('desafio'),
            'codigo'  => Mail::sent(CodigoLoginMail::class)->last()->codigo,
        ]);
    }
}
