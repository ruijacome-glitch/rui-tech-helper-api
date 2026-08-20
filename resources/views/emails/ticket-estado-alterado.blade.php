{{-- resources/views/emails/ticket-estado-alterado.blade.php --}}
<p>Ola {{ $ticket->cliente->nome }},</p>

<p>O estado do seu pedido "{{ $ticket->titulo }}" foi actualizado para:
<strong>{{ $evento->estado_novo->value }}</strong></p>

@if($evento->observacao_visivel_cliente && $evento->observacao)
    <p>{{ $evento->observacao }}</p>
@endif

<p>- O Rui dos Computadores</p>
