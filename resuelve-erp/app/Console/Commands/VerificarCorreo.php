<?php

namespace App\Console\Commands;

use App\Mail\PqrEventMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class VerificarCorreo extends Command
{
    protected $signature = 'resuelve:verificar-correo {--destino=gestionpqrs7@gmail.com} {--enviar : Enviar un mensaje de prueba} {--mailer= : Transporte configurado; mailpit para pruebas locales}';

    protected $description = 'Comprueba la configuración de correo y permite enviar una prueba explícita sin mostrar credenciales.';

    public function handle(): int
    {
        $mailer = $this->option('mailer') ?: config('mail.default');
        $local = $mailer === 'mailpit';
        if ($local && ! app()->environment('local', 'testing')) {
            $this->error('Mailpit solo se permite en desarrollo o pruebas.');

            return self::FAILURE;
        }
        $destino = $this->option('destino');
        if (! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
            $this->error('El destino debe ser una dirección de correo válida.');

            return self::FAILURE;
        }
        if (! in_array($mailer, ['smtp', 'mailpit'], true)) {
            $this->warn('El correo no tiene un transporte SMTP de entrega real activo. Configura el proveedor antes de enviar.');

            return self::FAILURE;
        }
        if ($mailer === 'smtp' && (! config('mail.mailers.smtp.username') || ! config('mail.mailers.smtp.password'))) {
            $this->warn('Faltan las credenciales SMTP. Para Gmail utiliza una contraseña de aplicación de la cuenta remitente.');

            return self::FAILURE;
        }
        if (! $this->option('enviar')) {
            $this->info('Configuración disponible. Usa --enviar para comprobar la entrega al proveedor; confirma después la recepción en el buzón.');

            return self::SUCCESS;
        }
        try {
            Mail::mailer($mailer)->to($destino)->send(new PqrEventMail(
                'Prueba de correo de Resuelve ERP',
                'Este mensaje verifica el transporte de correo de Resuelve ERP. No contiene información de residentes.',
                url('/iniciar-sesion'),
            ));
        } catch (Throwable $exception) {
            Log::warning('No se completó la prueba de correo de Resuelve ERP.', ['tipo_error' => $exception::class]);
            $this->error('El proveedor no aceptó la prueba. Revisa las credenciales y la conexión; no se ha acreditado la entrega.');

            return self::FAILURE;
        }
        $this->info($local
            ? 'Mensaje aceptado en Mailpit. Esta prueba local no acredita recepción en Gmail.'
            : 'El proveedor aceptó el mensaje de prueba. Confirma su recepción en el buzón para acreditar la entrega.');

        return self::SUCCESS;
    }
}
