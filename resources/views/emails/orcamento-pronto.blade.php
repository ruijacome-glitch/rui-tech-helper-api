@extends('emails.layout')

@section('subject', 'Orçamento pronto para aprovação')

@section('content')
    <p style="margin:0 0 4px 0;">Olá {{ $ticket->cliente->nome }},</p>

    <p style="margin:0 0 20px 0;">Já temos orçamento pronto para o teu pedido <strong style="color:#0F1B2E;">"{{ $ticket->titulo }}"</strong>:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin: 0 0 20px 0;">
        <tr>
            <td style="background-color:#f8fafc; padding: 10px 16px; border-bottom:1px solid #e5e7eb; color:#64748b; font-size:11px; font-weight:bold; letter-spacing:.6px; text-transform:uppercase;">Item</td>
            <td style="background-color:#f8fafc; padding: 10px 16px; border-bottom:1px solid #e5e7eb; color:#64748b; font-size:11px; font-weight:bold; letter-spacing:.6px; text-transform:uppercase; text-align:right;">Valor</td>
        </tr>
        @foreach($orcamento->itens as $item)
            <tr>
                <td style="padding: 10px 16px; border-bottom:1px solid #e5e7eb; font-size:14px; color:#334155;">{{ $item->descricao }} &times; {{ $item->quantidade }}</td>
                <td style="padding: 10px 16px; border-bottom:1px solid #e5e7eb; font-size:14px; color:#334155; text-align:right; font-variant-numeric: tabular-nums;">{{ number_format((float) $item->preco_unitario, 2) }}€</td>
            </tr>
        @endforeach
        <tr>
            <td style="padding: 14px 16px; font-weight:bold; font-size:16px; color:#0F1B2E;">Total</td>
            <td style="padding: 14px 16px; font-weight:bold; font-size:16px; color:#0F1B2E; text-align:right;">{{ number_format($total, 2) }}€</td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 4px 0 20px 0;">
                <a href="{{ $portalUrl }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 14px 28px; border-radius:6px; border-bottom:2px solid #1d5fd6; font-weight:bold; font-size:14px; display:inline-block;">Ver e aprovar orçamento</a>
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#64748b; font-size:13px;">— O Rui dos Computadores</p>
@endsection
