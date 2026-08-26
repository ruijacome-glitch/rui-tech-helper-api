@extends('emails.layout')

@section('subject', 'Atualização do seu pedido')

@section('content')
    <p>Olá {{ $ticket->cliente->nome }},</p>

    @if($evento->estado_novo->value === 'cancelado')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fee2e2; border-radius:6px; margin: 0 0 16px 0;">
            <tr>
                <td style="padding: 12px 16px; color:#991b1b; font-weight:bold; font-size:14px;">
                    Este ticket foi cancelado
                </td>
            </tr>
        </table>
    @endif

    <p>O estado do teu pedido "{{ $ticket->titulo }}" foi actualizado para:</p>

    <p style="text-align:center; margin: 16px 0;">
        <span style="background-color:#2E7FFF; color:#ffffff; padding: 8px 16px; border-radius:20px; font-weight:bold; font-size:14px; display:inline-block;">{{ $evento->estado_novo->label() }}</span>
    </p>

    @if($evento->observacao_visivel_cliente && $evento->observacao)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; border-radius:6px; margin: 16px 0;">
            <tr>
                <td style="padding: 12px 16px; font-size:14px; color:#374151;">
                    {{ $evento->observacao }}
                </td>
            </tr>
        </table>
    @endif

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $trackingUrl }}" style="background-color:#0F1B2E; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Acompanhar pedido</a>
    </p>

    <p>- O Rui dos Computadores</p>
@endsection
