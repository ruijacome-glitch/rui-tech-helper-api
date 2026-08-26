@extends('emails.layout')

@section('subject', 'Orçamento pronto para aprovação')

@section('content')
    <p>Olá {{ $ticket->cliente->nome }},</p>

    <p>Já temos orçamento pronto para o teu pedido "{{ $ticket->titulo }}":</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin: 16px 0;">
        @foreach($orcamento->itens as $item)
            <tr>
                <td style="padding: 8px 0; border-bottom:1px solid #e5e7eb; font-size:14px;">{{ $item->descricao }} &times; {{ $item->quantidade }}</td>
                <td style="padding: 8px 0; border-bottom:1px solid #e5e7eb; font-size:14px; text-align:right;">{{ number_format((float) $item->preco_unitario, 2) }}€</td>
            </tr>
        @endforeach
        <tr>
            <td style="padding: 12px 0 0 0; font-weight:bold; font-size:16px;">Total</td>
            <td style="padding: 12px 0 0 0; font-weight:bold; font-size:16px; text-align:right;">{{ number_format($total, 2) }}€</td>
        </tr>
    </table>

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $portalUrl }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Ver e aprovar orçamento</a>
    </p>

    <p>- O Rui dos Computadores</p>
@endsection
