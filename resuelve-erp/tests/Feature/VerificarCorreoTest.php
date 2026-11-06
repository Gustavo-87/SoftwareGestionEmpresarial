<?php

namespace Tests\Feature;

use App\Mail\PqrEventMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class VerificarCorreoTest extends TestCase
{
    public function test_mailpit_permite_probar_sin_credenciales_pero_no_en_produccion(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $this->artisan('resuelve:verificar-correo', ['--mailer' => 'mailpit', '--enviar' => true])->assertSuccessful();
        Mail::assertSent(PqrEventMail::class, fn ($mail) => $mail->hasTo('gestionpqrs7@gmail.com'));
        $this->app['env'] = 'production';
        $this->artisan('resuelve:verificar-correo', ['--mailer' => 'mailpit', '--enviar' => true])->assertFailed();
    }

    public function test_log_no_se_presenta_como_entrega_real(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $this->artisan('resuelve:verificar-correo', ['--enviar' => true])->assertFailed();
        Mail::assertNothingSent();
    }

    public function test_smtp_sin_credenciales_no_envia(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'gestionpqrs7@gmail.com', 'mail.mailers.smtp.password' => null]);
        $this->artisan('resuelve:verificar-correo', ['--enviar' => true])->assertFailed();
        Mail::assertNothingSent();
    }

    public function test_diagnosticar_no_envia_y_prueba_explicita_usa_el_destino_indicado(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'gestionpqrs7@gmail.com', 'mail.mailers.smtp.password' => 'credencial-de-prueba']);
        $this->artisan('resuelve:verificar-correo')->assertSuccessful();
        Mail::assertNothingSent();
        $this->artisan('resuelve:verificar-correo', ['--enviar' => true])->assertSuccessful();
        Mail::assertSent(PqrEventMail::class, fn ($mail) => $mail->hasTo('gestionpqrs7@gmail.com'));
    }
}
