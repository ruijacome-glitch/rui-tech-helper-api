<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketCriado extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Ticket $ticket)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recebemos o teu pedido - O Rui dos Computadores',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-criado',
            with: [
                'ticket' => $this->ticket,
                'trackingUrl' => rtrim(config('services.tracking_url'), '/').'/t/'.$this->ticket->tracking_token,
            ],
        );
    }
}
