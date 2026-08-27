@extends('emails.layout')

@section('subject', 'Pagamento recebido')

@section('content')
    <p style="margin:0 0 4px 0;">Olá {{ $ticket->cliente->nome }},</p>

    <p style="margin:0 0 20px 0;">Confirmamos a receção do teu pagamento referente ao pedido <strong style="color:#0F1B2E;">"{{ $ticket->titulo }}"</strong>.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; margin: 0 0 20px 0;">
        <tr>
            <td style="padding: 14px 16px; border-bottom:1px solid #e5e7eb; font-size:14px; color:#334155;">Valor pago</td>
            <td style="padding: 14px 16px; border-bottom:1px solid #e5e7eb; font-size:14px; color:#0F1B2E; text-align:right; font-weight:bold; font-variant-numeric: tabular-nums;">{{ number_format((float) $pagamento->valor, 2) }}€</td>
        </tr>
        <tr>
            <td style="padding: 14px 16px; font-size:14px; color:#334155;">Data</td>
            <td style="padding: 14px 16px; font-size:14px; color:#334155; text-align:right;">{{ $pagamento->paid_at?->format('d/m/Y H:i') }}</td>
        </tr>
    </table>

    <p style="margin:0 0 20px 0;">A fatura/recibo será enviada num email separado assim que estiver emitida.</p>

    <p style="margin:0; color:#64748b; font-size:13px;">— O Rui dos Computadores</p>
@endsection
