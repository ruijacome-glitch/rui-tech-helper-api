{{-- resources/views/emails/convite-cliente.blade.php --}}
@extends('emails.layout')

@section('subject', 'Ativa a tua conta')

@section('content')
    <p style="margin:0 0 4px 0;">Olá {{ $cliente->nome }},</p>

    <p style="margin:0 0 20px 0;">Foi criada uma ficha para ti no sistema d'O Rui dos Computadores. Para veres o estado das tuas intervenções e definires a tua password, clica no botão abaixo:</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding: 4px 0 16px 0;">
                <a href="{{ $url }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 14px 28px; border-radius:6px; border-bottom:2px solid #1d5fd6; font-weight:bold; font-size:14px; display:inline-block;">Ativar conta</a>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8fafc; border:1px solid #e5e7eb; border-radius:8px; margin: 0 0 20px 0;">
        <tr>
            <td style="padding: 12px 16px; color:#64748b; font-size:13px;">
                ⏱ Este link expira em 7 dias.
            </td>
        </tr>
    </table>

    <p style="margin:0; color:#64748b; font-size:13px;">— O Rui dos Computadores</p>
@endsection
