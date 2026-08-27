<?php

namespace App\Mail;

use App\Models\Pagamento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PagamentoRecebido extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Pagamento $pagamento)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Pagamento recebido - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pagamento-recebido',
            with: [
                'ticket' => $this->pagamento->orcamento->ticket,
                'pagamento' => $this->pagamento,
            ],
        );
    }
}
