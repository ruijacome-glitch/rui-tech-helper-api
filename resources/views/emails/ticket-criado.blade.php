@extends('emails.layout')

@section('subject', 'Recebemos o teu pedido')

@section('content')
    <p style="margin:0 0 4px 0;">Olá {{ $ticket->cliente->nome }},</p>

    <p style="margin:0 0 20px 0;">Recebemos o teu pedido e já está registado no nosso sistema.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; margin: 0 0 20px 0;">
        <tr>
            <td style="padding: 18px 20px;">
                <p style="margin:0 0 4px 0; color:#64748b; font-size:11px; font-weight:bold; letter-spacing:.6px; text-transform:uppercase;">Pedido</p>
                <p style="margin:0 0 10px 0; font-weight:bold; font-size:16px; color:#0F1B2E;">{{ $ticket->titulo }}</p>
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:0 12px 0 0; color:#64748b; font-size:13px;">Categoria: <strong style="color:#334155;">{{ ucfirst($ticket->categoria->value) }}</strong></td>
                        <td style="color:#64748b; font-size:13px;">Prioridade: <strong style="color:#334155;">{{ ucfirst($ticket->prioridade->value) }}</strong></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 20px 0;">{{ $ticket->descricao }}</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 4px 0 20px 0;">
                <a href="{{ $trackingUrl }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 14px 28px; border-radius:6px; border-bottom:2px solid #1d5fd6; font-weight:bold; font-size:14px; display:inline-block;">Acompanhar pedido</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#64748b; font-size:13px;">— O Rui dos Computadores</p>
@endsection
