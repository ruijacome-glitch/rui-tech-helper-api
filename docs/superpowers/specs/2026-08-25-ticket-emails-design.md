# Emails HTML profissionais — ticket lifecycle — Design

**Sub-projecto** (fora da sequência 1-9 principal, trabalho pontual pedido directamente). Redesenha os emails que o cliente recebe ao longo do ciclo de vida do ticket, com branding consistente.

## Goal

1. Header/footer partilhado com logo + cores da marca em todos os emails do cliente.
2. Novo email de "ticket criado" (não existia).
3. Melhorar `ticket-estado-alterado`, `orcamento-pronto`, `convite-cliente` com o mesmo layout, labels PT legíveis, e link de tracking onde fizer sentido.

## Scope

**Dentro:**
- Layout Blade partilhado (`resources/views/emails/layout.blade.php`).
- Logo PNG (240px, exportado do SVG existente via Inkscape) em `public/images/logo-email.png`.
- `TicketEstado::label(): string` — labels PT.
- Novo `App\Mail\TicketCriado` + view, disparado em `Ticket::created`.
- Redesign de `ticket-estado-alterado`, `orcamento-pronto`, `convite-cliente` (mesmo layout).
- `config('services.tracking_url')` + env `TRACKING_URL`.
- Testes Pest para o novo email + labels.

**Fora:**
- `novo-pedido-contacto` (email interno, não é do cliente) — sem alterações.
- Página de tracking pública em si (`tracking.oruidoscomputadores.pt`) — sub-projecto 8c, ainda não construída. O link no email vai apontar pra lá mesmo antes de existir (decisão já tomada no brainstorming do sub-projecto 8b).
- Preferências de email do cliente (opt-out) — não pedido, YAGNI.
- Testes visuais automatizados / screenshot testing de email — sem test runner de email neste projecto, consistente com o resto.

## Decisões de design

### Layout partilhado

`resources/views/emails/layout.blade.php` com uma `@yield('content')` (ou slot), usado por `@extends('emails.layout')` em cada template. Tabelas HTML (não flexbox/grid) para compatibilidade com Outlook. CSS inline nos elementos-chave (não `<style>` externo — muitos clientes cortam `<head>`).

Estrutura:
```
┌─────────────────────────────────┐
│  [navy bg] logo PNG centrado     │  ← header
├─────────────────────────────────┤
│  @yield('content')               │  ← branco/cinza claro, texto normal
├─────────────────────────────────┤
│  O Rui dos Computadores          │  ← footer, cinza claro
│  ola@oruidoscomputadores.pt      │
└─────────────────────────────────┘
```

Cores: header `#0F1B2E` (navy do logo), destaque/botões `#2E7FFF` (azul do logo), texto corpo `#1a1a1a` sobre `#ffffff`.

### Logo

Exportar `rui-tech-helper/src/assets/logo-rui.svg` → PNG 240px via Inkscape (já testado, ~28KB), colocar em `rui-tech-helper-api/public/images/logo-email.png`. Referenciado no layout via `config('app.url').'/images/logo-email.png'` — URL absoluta e estável (`api.oruidoscomputadores.pt/images/logo-email.png`), sem depender de asset pipeline.

### `TicketEstado::label()`

```php
public function label(): string
{
    return match ($this) {
        self::Aberto => 'Recebido',
        self::EmAnalise => 'Em Diagnóstico',
        self::AguardaPeca => 'Aguarda Peças',
        self::EmCurso => 'Em Reparação',
        self::EmTestes => 'Reparação Concluída',
        self::AguardaCliente => 'Pronto p/ Levantamento',
        self::Resolvido => 'Entregue',
        self::Cancelado => 'Cancelado',
    };
}
```
Usado em `ticket-estado-alterado` (e potencialmente noutros pontos futuros, mas só este email precisa agora).

### `TicketCriado`

Disparado no `Ticket::booted()`, evento `created` — mesmo sítio onde `tracking_token` já é gerado. Mesmo padrão try/catch + `Log::error` que `mudarEstado()` já tem (fix anterior desta sessão). Conteúdo: saudação, título/categoria/prioridade/descrição do ticket, botão "Acompanhar pedido" → `{tracking_url}/t/{tracking_token}`.

Nota: dispara tanto em `store()` (admin cria) como `storeCliente()` (cliente cria) — evento `created` do model cobre os dois sem duplicar lógica no controller.

### `ticket-estado-alterado` — redesign

- Badge com `$evento->estado_novo->label()` em vez do valor cru do enum.
- Se `estado_novo === TicketEstado::Cancelado`: faixa vermelha de aviso acima do badge ("Este ticket foi cancelado").
- Observação (se `observacao_visivel_cliente`) em bloco citação.
- Botão "Acompanhar pedido" com o mesmo link de tracking.

### `orcamento-pronto` / `convite-cliente` — redesign

Mesma estrutura de layout; conteúdo existente (tabela de itens + total; texto + link de activação) migrado para o novo layout, com botão estilizado em vez de link cru.

### `config('services.tracking_url')`

```php
// config/services.php
'tracking_url' => env('TRACKING_URL', 'https://tracking.oruidoscomputadores.pt'),
```
Usado nos dois emails que linkam pra tracking, em vez de hardcode — permite override em `.env` local/staging.

## Data flow

```
Ticket::create() (store ou storeCliente)
  → Ticket::booted() 'created' event
    → try: Mail::to(cliente.email)->send(new TicketCriado($ticket))
    → catch: Log::error(...), não bloqueia a resposta HTTP

Ticket::mudarEstado()
  → (já existente, já com try/catch) envia TicketEstadoAlterado com layout novo

OrcamentoController::store() → envia OrcamentoPronto com layout novo (try/catch já existe)
ConviteController → envia ConviteCliente com layout novo
```

## Error handling

Todos os 4 sends seguem o padrão já estabelecido nesta sessão: try/catch + `Log::error` com contexto (id do recurso + mensagem do erro), nunca deixa uma falha de mail bloquear a operação principal. Confirmado por leitura directa do código: **nenhum dos dois tem isto ainda** — `OrcamentoController::store()` (`Mail::to()->send()` sem try/catch) e `ClienteController` (`ConviteCliente`, idem) ficam a receber o mesmo tratamento nesta ronda, consistente com o fix já aplicado a `Ticket::mudarEstado()`.

## Testing

- **Pest**: `TicketCriadoTest` — email disparado em `store()` e `storeCliente()`, assert subject + conteúdo (título do ticket, link de tracking com token correcto). `TicketEstadoLabelTest` — cada case do enum devolve label PT esperado. Assert visual de "cancelado" com faixa (procurar string de aviso no HTML renderizado).
- **Manual**: preview local dos 4 templates (Laravel tem `Mail::to(...)->send(...)` testável via `php artisan tinker` ou rota de preview temporária) — confirmar logo aparece, cores correctas, botões clicáveis, sem quebra de layout no Gmail (view HTML no browser é suficiente, sem client de email real disponível neste ambiente).

## Out of scope (explícito)

- Página de tracking pública (8c) — link fica "morto" até essa página existir, aceite no brainstorming do 8b.
- `novo-pedido-contacto` (email interno) — sem alterações.
- Opt-out/preferências de email — não pedido.
- Testes de renderização em clientes de email reais (Litmus/Email on Acid) — sem acesso a essas ferramentas, verificação manual via HTML no browser é o que está disponível.
