<p>Ola {{ $ticket->cliente->nome }},</p>

<p>Ja temos orcamento pronto para o pedido "{{ $ticket->titulo }}":</p>

<ul>
@foreach($orcamento->itens as $item)
    <li>{{ $item->descricao }} - {{ $item->quantidade }} x {{ number_format((float) $item->preco_unitario, 2) }}EUR</li>
@endforeach
</ul>

<p><strong>Total: {{ number_format($total, 2) }}EUR</strong></p>

<p><a href="{{ $portalUrl }}">Ver e aprovar orcamento</a></p>

<p>- O Rui dos Computadores</p>
