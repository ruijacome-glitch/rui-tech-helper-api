<?php

namespace App\Mail;

use App\Models\Orcamento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrcamentoPronto extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Orcamento $orcamento)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Orcamento pronto para aprovacao - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.orcamento-pronto',
            with: [
                'ticket' => $this->orcamento->ticket,
                'orcamento' => $this->orcamento,
                'total' => $this->orcamento->total(),
                'portalUrl' => config('app.frontend_url', 'https://oruidoscomputadores.pt') . '/portal/tickets/' . $this->orcamento->ticket_id,
            ],
        );
    }
}
