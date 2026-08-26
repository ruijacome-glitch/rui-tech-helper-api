{{-- resources/views/emails/convite-cliente.blade.php --}}
@extends('emails.layout')

@section('subject', 'Ativa a tua conta')

@section('content')
    <p>Olá {{ $cliente->nome }},</p>

    <p>Foi criada uma ficha para ti no sistema d'O Rui dos Computadores. Para veres o estado das tuas intervenções e definires a tua password, clica no botão abaixo:</p>

    <p style="text-align:center; margin: 24px 0;">
        <a href="{{ $url }}" style="background-color:#2E7FFF; color:#ffffff; text-decoration:none; padding: 12px 24px; border-radius:6px; font-weight:bold; display:inline-block;">Ativar conta</a>
    </p>

    <p style="color:#6b7280; font-size:13px;">Este link expira em 7 dias.</p>

    <p>- O Rui dos Computadores</p>
@endsection
