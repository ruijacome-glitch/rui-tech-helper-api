# Financeiro — Pagamentos (IfthenPay) + Facturação (Moloni)

**Data:** 2026-08-20
**Estado:** Aprovado, pronto para plano de implementação.

## Contexto

Sub-projecto 3 do roadmap `rui-tech-helper-api`. Sub-projectos 1 (fundação) e 2 (CRM core) estão feitos e deployados. `Orcamento`/`OrcamentoItem` já existem — cliente pode aceitar um orçamento (`OrcamentoController::decisao()`). Este sub-projecto liga "orçamento aceite" a pagamento real (IfthenPay) e documento fiscal (Moloni).

## Requisito legal — ordem factura/pagamento

Nenhum documento fiscal é emitido antes do pagamento ser confirmado. Emitir uma Factura antes do pagamento cria risco de facturas comunicadas à AT sem correspondência de pagamento (cliente não paga → factura fica "pendurada"). Por isso: só existe **Factura-Recibo (FR)**, emitida num único passo, só depois de confirmado o pagamento.

## Regime de IVA

Isento de IVA actualmente, mas pode mudar no futuro. Guardado em `config/fiscal.php`, lido de variáveis de ambiente:
- `FISCAL_ISENTO_IVA` (bool)
- `FISCAL_IVA_TAXA` (int, usado quando `FISCAL_ISENTO_IVA=false`)
- `FISCAL_MOTIVO_ISENCAO` (string, texto legal a incluir no documento quando isento)

Mudar de regime no futuro é só alterar `.env`, sem migração de schema.

## Modelo de dados

### Tabela `pagamentos`
Uma linha por orçamento aceite (1:1 com `orcamentos`).

| campo | tipo | notas |
|---|---|---|
| `orcamento_id` | FK, unique | 1:1 com `orcamentos` |
| `metodo` | enum `mb`\|`mbway` | nullable até cliente escolher |
| `estado` | enum `pendente`\|`pago`\|`expirado`\|`cancelado` | |
| `ifthenpay_request_id` | string, nullable | id devolvido pela IfthenPay |
| `entidade` | string, nullable | entidade Multibanco (método `mb`) |
| `referencia` | string, nullable | referência Multibanco (método `mb`) |
| `telefone` | string, nullable | telefone MB WAY (método `mbway`) |
| `valor` | decimal | copiado do total do orçamento no momento da geração |
| `expires_at` | datetime, nullable | `+48h` a partir da geração |
| `paid_at` | datetime, nullable | |
| `origem` | enum `ifthenpay`\|`manual` | como foi confirmado o pagamento |
| `moloni_document_id` | string, nullable | id do documento na Moloni |
| `moloni_numero_documento` | string, nullable | número legível (ex: `FR 2026/12`) |

Regeneração de referência (após expirar): reutiliza a mesma linha `Pagamento`, sobrescreve `metodo`/`entidade`/`referencia`/`telefone`/`expires_at`/`ifthenpay_request_id`, volta a `estado=pendente`. Sem tabela de histórico separada (YAGNI — pode adicionar-se depois se for preciso auditoria fina).

### Tabela `moloni_credentials`
Uma única linha, credenciais OAuth2 da empresa na Moloni.

| campo | tipo |
|---|---|
| `access_token` | string |
| `refresh_token` | string |
| `expires_at` | datetime |

Renovado automaticamente antes de cada chamada à API Moloni se `expires_at` já passou.

### Enums novos
- `App\Enums\PagamentoMetodo`: `Mb = 'mb'`, `Mbway = 'mbway'`
- `App\Enums\PagamentoEstado`: `Pendente = 'pendente'`, `Pago = 'pago'`, `Expirado = 'expirado'`, `Cancelado = 'cancelado'`
- `App\Enums\PagamentoOrigem`: `Ifthenpay = 'ifthenpay'`, `Manual = 'manual'`

## Fluxo

1. **Aceitar orçamento** (`OrcamentoController::decisao()`, já existe) — adicionar guarda: se `orcamento->ticket->cliente->nif` vazio, `422` com mensagem a pedir para completar o perfil antes de aceitar. Ao aceitar, cria `Pagamento(orcamento_id, estado=pendente)`.

2. **Escolher método de pagamento** — `POST /api/orcamentos/{orcamento}/pagamento`
   - Body: `{ metodo: 'mb'|'mbway', telefone?: string }` (`telefone` obrigatório se `metodo=mbway`)
   - Chama `IfthenPayService`:
     - `mb`: gera entidade+referência estática (válida até `expires_at`), sem acção do cliente necessária além de pagar no MB/homebanking.
     - `mbway`: envia pedido push para o `telefone` indicado; cliente aprova na app.
   - Grava resposta na `Pagamento`, `expires_at = now()->addHours(48)`.
   - Idempotência: se já existe pedido `pendente` não expirado para este orçamento, devolve o existente em vez de gerar novo.

3. **Confirmação de pagamento** — dois caminhos, mesma acção final:
   - **Automático**: `POST /api/webhooks/ifthenpay` — callback IfthenPay. Valida contra chave anti-phishing configurada (`services.ifthenpay.antiphishing_key`). Identifica o `Pagamento` pelo `referencia`/`ifthenpay_request_id` no payload. Idempotente: se já `estado=pago`, responde `200` sem reprocessar.
   - **Manual**: `POST /api/orcamentos/{orcamento}/pagamento/marcar-pago` — só `role=admin`. Regista `origem=manual`.
   - Em ambos: `Pagamento->update(estado: pago, paid_at: now())`, despacha `EmitirFacturaRecibo::dispatch($pagamento)` (fila `database`, já configurada).

4. **Job `EmitirFacturaRecibo`** — `tries=3`, backoff exponencial.
   - Renova token Moloni se `expires_at` passado (`MoloniService::garantirToken()`).
   - Cria documento Factura-Recibo com: dados do cliente (nome, NIF, morada), itens copiados de `orcamento->itens` (descrição, quantidade, preço unitário), taxa de IVA de `config('fiscal')`.
   - Grava `moloni_document_id`/`moloni_numero_documento` no `Pagamento`.
   - Envia email ao cliente (Resend, mailable novo `FacturaEmitida`) com link/PDF do documento (Moloni devolve URL do PDF).
   - Se a chamada Moloni falhar, o job falha e a fila tenta de novo automaticamente (até 3x); `Pagamento` fica `estado=pago` mas `moloni_document_id=null` entretanto — visível no CRM como "pago, factura pendente de emissão".

5. **Expiração** — quando `expires_at` passa sem pagamento, `Pagamento->estado` passa a `expirado` (verificado ao ler, sem cron dedicado por agora — YAGNI). Admin vê no CRM, pode chamar de novo o endpoint do passo 2 para gerar nova referência.

6. **OAuth Moloni** — `GET /api/webhooks/moloni/callback?code=...` recebe o `code` da autorização inicial (feita manualmente uma vez pelo admin, via link de autorização Moloni), troca por `access_token`+`refresh_token`, grava em `moloni_credentials`. Endpoint só usado uma vez no setup inicial (e se for preciso reautorizar).

## Portal cliente

Cliente vê estado do pagamento (pendente/pago, referência, prazo) no portal (site Lovable, consumindo API já existente de tickets/orçamentos — sem UI nova neste sub-projecto, só os dados ficam disponíveis via API). Email enviado em dois momentos: ao gerar referência (resumo + dados de pagamento) e ao confirmar pagamento (factura anexa/link).

## Componentes novos

- **Migrations**: `create_pagamentos_table`, `create_moloni_credentials_table`
- **Enums**: `PagamentoMetodo`, `PagamentoEstado`, `PagamentoOrigem`
- **Model**: `Pagamento` (belongsTo `Orcamento`)
- **Services**: `IfthenPayService` (gerar referência MB, gerar pedido MBWay), `MoloniService` (OAuth token management, criar Factura-Recibo)
- **Job**: `EmitirFacturaRecibo`
- **Controllers**: `PagamentoController` (`store`, `marcarPago`), `WebhookController` (`ifthenpay`, `moloniCallback`)
- **Mail**: `FacturaEmitida`
- **Config**: `config/fiscal.php`, entradas `ifthenpay`/`moloni` em `config/services.php`

## Erros

| Situação | Comportamento |
|---|---|
| Moloni em baixo ao emitir FR | Job falha, retry automático (3x); `Pagamento` fica `pago` sem `moloni_document_id`, admin vê aviso |
| IfthenPay falha ao gerar referência | `422`/`500` devolvido ao cliente, pode tentar de novo |
| Callback IfthenPay duplicado | No-op se já `pago` |
| Callback IfthenPay com chave anti-phishing inválida | `403`, não processa |
| Cliente sem NIF tenta aceitar orçamento | `422`, pede para completar perfil |
| Referência expira sem pagamento | `estado=expirado`, admin gera nova |

## Testes

Pest, `Http::fake()` a mockar Moloni + IfthenPay:
- Aceitar orçamento sem NIF → bloqueado
- Gerar referência MB → grava entidade/referência/expiração
- Gerar pedido MBWay → grava telefone/pedido
- Callback IfthenPay válido → marca pago, despacha job, idempotente em duplicado
- Callback IfthenPay com chave inválida → rejeitado
- Marcar pago manual (admin) → mesmo efeito que callback, `origem=manual`
- Job `EmitirFacturaRecibo` → cria documento Moloni, grava id, envia email
- Job falha (Moloni indisponível) → retry, `Pagamento` mantém-se `pago` sem documento

## Fora de âmbito (YAGNI, por agora)

- Histórico de tentativas de pagamento (só o estado actual é guardado)
- Cron para marcar `expirado` automaticamente (calculado on-read)
- UI de portal cliente (dados só ficam disponíveis via API; UI é sub-projecto 5)
- Notas de crédito / anulação de facturas
- Pagamento por cartão
