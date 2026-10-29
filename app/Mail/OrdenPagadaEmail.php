<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

use App\Models\Quote;
use Illuminate\Support\Facades\Storage;

class OrdenPagadaEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;

    public function __construct(Quote $quote)
    {
        // Se cargan las relaciones de pagos para armar el resumen
        $this->quote = $quote->load('pagos');
    }

    public function build()
    {
        $email = $this->subject("Orden #{$this->quote->folio} Pagada Totalmente")
                     ->view('emails.orden_pagada');

        // Adjuntar los archivos de comprobantes presentes en los pagos por transferencia
        foreach ($this->quote->pagos as $pago) {
            if ($pago->tipo === 'transferencia' && $pago->comprobante) {
                // $filePath = storage_path('app/public/' . $pago->comprobante);

                // La ruta completa en public_path
                $filePath = public_path($pago->comprobante);
                
                if (file_exists($filePath)) {
                    $email->attach($filePath);
                }
            }
        }

        return $email;
    }
}
