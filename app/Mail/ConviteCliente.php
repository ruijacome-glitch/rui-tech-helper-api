<?php

namespace App\Mail;

use App\Models\Convite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConviteCliente extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Convite $convite,
        public string $plaintextToken,
    ) {}

    public function build()
    {
        $url = rtrim(config('services.frontend_url'), '/')."/ativar-conta/{$this->plaintextToken}";

        return $this->subject('Ativa a tua conta — O Rui dos Computadores')
            ->view('emails.convite-cliente', ['url' => $url, 'cliente' => $this->convite->cliente]);
    }
}
