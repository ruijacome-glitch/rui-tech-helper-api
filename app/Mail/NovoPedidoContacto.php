<?php

namespace App\Mail;

use App\Models\PedidoContacto;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NovoPedidoContacto extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PedidoContacto $pedido,
    ) {}

    public function build()
    {
        return $this->subject('Novo pedido de contacto — '.$this->pedido->nome)
            ->replyTo($this->replyToValor())
            ->view('emails.novo-pedido-contacto', ['pedido' => $this->pedido]);
    }

    private function replyToValor(): string
    {
        return str_contains($this->pedido->contacto_valor, '@')
            ? $this->pedido->contacto_valor
            : config('mail.from.address');
    }
}
