@extends('emails.layout')

@section('subject', 'Recebemos o teu pedido')

@section('content')
    <p>Olá {{ $ticket->cliente->nome }},</p>

    <p>Recebemos o teu pedido e já está no nosso sistema:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; border-radius:6px; margin: 16px 0;">
        <tr>
            <td style="padding: 16px;">
                <p style="margin:0 0 8px 0; font-weight:bold; font-size:16px;">{{ $ticket->titulo }}</p>
                <p style="margin:0; color:#6b7280; font-size:13px;">
                    Categoria: {{ ucfirst($ticket->categoria->value) }} &middot; Prioridade: {{ ucfirst($ticket->prioridade->value) }}
                </p>
            </td>
        </tr>
    </table>

    <p>{{ $ticket->descricao }}</p>

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $trackingUrl }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Acompanhar pedido</a>
    </p>

    <p>- O Rui dos Computadores</p>
@endsection
