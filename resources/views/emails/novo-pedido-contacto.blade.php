<p><strong>Nome:</strong> {{ $pedido->nome }}</p>
<p><strong>Contacto:</strong> {{ $pedido->contacto_valor }} (prefere {{ $pedido->preferencia }})</p>
<p><strong>Localidade:</strong> {{ $pedido->localidade }}</p>
@if ($pedido->problema)
    <p><strong>Tipo de problema:</strong> {{ $pedido->problema }}</p>
@endif
@if ($pedido->periodo)
    <p><strong>Melhor período:</strong> {{ $pedido->periodo }}</p>
@endif
<p><strong>Mensagem:</strong></p>
<p>{{ $pedido->mensagem }}</p>
