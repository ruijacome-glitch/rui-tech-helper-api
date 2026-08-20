<?php
// app/Mail/TicketEstadoAlterado.php
namespace App\Mail;

use App\Models\TicketEvento;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketEstadoAlterado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TicketEvento $evento)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Atualizacao do seu pedido - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-estado-alterado',
            with: [
                'ticket' => $this->evento->ticket,
                'evento' => $this->evento,
            ],
        );
    }
}
