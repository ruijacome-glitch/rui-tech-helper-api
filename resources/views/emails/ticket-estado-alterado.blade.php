@extends('emails.layout')

@section('subject', 'Atualização do seu pedido')

@section('content')
    <p style="margin:0 0 4px 0;">Olá {{ $ticket->cliente->nome }},</p>

    @if($evento->estado_novo->value === 'cancelado')
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#fef2f2; border:1px solid #fecaca; border-radius:8px; margin: 16px 0;">
            <tr>
                <td style="padding: 14px 18px; color:#991b1b; font-weight:bold; font-size:14px;">
                    ⚠ Este ticket foi cancelado
                </td>
            </tr>
        </table>
    @endif

    <p style="margin:16px 0;">O estado do teu pedido <strong style="color:#0F1B2E;">"{{ $ticket->titulo }}"</strong> foi atualizado para:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 4px 0 20px 0;">
                <span style="background-color:#eaf1ff; color:#1d5fd6; border:1px solid #b9d3ff; padding: 8px 18px; border-radius:20px; font-weight:bold; font-size:13px; display:inline-block;">{{ $evento->estado_novo->label() }}</span>
            </td>
        </tr>
    </table>

    @if($evento->observacao_visivel_cliente && $evento->observacao)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border-left:3px solid #2E7FFF; border-radius:0 8px 8px 0; margin: 0 0 20px 0;">
            <tr>
                <td style="padding: 14px 18px; font-size:14px; color:#334155;">
                    {{ $evento->observacao }}
                </td>
            </tr>
        </table>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 4px 0 20px 0;">
                <a href="{{ $trackingUrl }}" style="background-color:#0F1B2E; color:#ffffff; text-decoration:none; padding: 14px 28px; border-radius:6px; border-bottom:2px solid #060c15; font-weight:bold; font-size:14px; display:inline-block;">Acompanhar pedido</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#64748b; font-size:13px;">— O Rui dos Computadores</p>
@endsection
