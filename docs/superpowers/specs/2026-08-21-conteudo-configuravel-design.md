# Conteúdo site configurável — design (fase 1)

Sub-projecto 4 do roadmap CRM "O Rui dos Computadores". Ver memória `rui-tech-helper-roadmap`.

## Contexto

Site público `rui-tech-helper` é estático (build-time, `node scripts/build-static.mjs`, zero fetch a runtime hoje). Conteúdo editorial vive em `src/data/site.ts` e `src/data/paginas.ts`, hardcoded. Objectivo: tornar parte deste conteúdo editável por backoffice, sem exigir rebuild/deploy do site a cada alteração de preço ou contacto.

## Scope (fase 1)

Apenas o que muda com mais frequência na prática:
- `contacto` (telefone, email, whatsapp) — hoje em `site.ts`.
- `precos` (3 linhas, secção home) + `precarioAreas` (4 linhas, página `/precario`) — hoje em `site.ts`, valores actualmente "Valor a confirmar"/"Mediante avaliação".
- `testemunhoExemplo` (citação + atribuição) — hoje em `site.ts`, marcado como placeholder a substituir.

Fora de scope (fase 1): tudo em `paginas.ts` (serviços detalhados, timeline "sobre", etc), `sintomas`, `servicos`, `passos`, `argumentos`, `negocios`, `parceiro`. Podem entrar em fase futura se o padrão de uso justificar.

Fora de scope: adicionar/remover linhas de preço (continua a exigir alteração de código — só valor+nota das linhas existentes são editáveis nesta fase). Rebuild automático do site ao gravar (site continua estático; conteúdo chega via fetch client-side, não via rebuild).

## Arquitectura

Aplicação `rui-tech-helper-api` (Laravel) ganha:
- Persistência: tabela `conteudos` (chave-valor genérica, JSON) para os 2 blocos singleton; tabela `precos` própria (linhas, com secção) para os itens de preço — porque são listas ordenadas com um item por linha, e uma tabela dá ordenação/validação por linha sem reinventar array-dentro-de-JSON.
- Um endpoint público (sem autenticação) que agrega tudo num único pedido, para o site não fazer N fetches.
- Uma página de administração (Blade, dentro da própria app Laravel — não um SPA novo) para o Rui editar os valores, atrás de login + role admin.
- No site público (`rui-tech-helper`), um fetch client-side no mount das páginas relevantes, com os valores estáticos actuais de `site.ts` mantidos no bundle como fallback caso o fetch falhe ou a API esteja em baixo.

Não se cria o repositório `rui-tech-helper-crm` (SPA de backoffice) nesta fase — não existe ainda no disco, e criar um SPA novo só para este formulário seria over-engineering. Fica reservado para sub-projecto 5 (conversão do frontend + CRM completo).

## Modelo de dados

**Tabela `conteudos`**
| coluna | tipo | notas |
|---|---|---|
| chave | string, PK | `contacto` \| `testemunho` |
| valor | json | estrutura livre por chave (ver abaixo) |
| timestamps | | |

- `contacto` → `{"telefone": "...", "email": "...", "whatsapp": "..."}`
- `testemunho` → `{"citacao": "...", "atribuicao": "..."}`

**Tabela `precos`**
| coluna | tipo | notas |
|---|---|---|
| id | bigint PK | |
| secao | enum(`home`, `precario`) | `home` = teaser 3 itens da homepage; `precario` = página `/precario` |
| servico | string | rótulo fixo, não editável no admin |
| valor | string | editável (ex. "35 €", "Valor a confirmar") |
| nota | text nullable | editável |
| ordem | int | posição de exibição |
| timestamps | | |

Seed inicial: as 3 linhas `home` a partir de `site.ts::precos`, as 4 linhas `precario` a partir de `site.ts::precarioAreas`, mantendo texto/ordem actuais.

## API pública

`GET /api/public/conteudo-site` (sem auth, sem rate-limit especial — só leitura):

```json
{
  "contacto": {"telefone": "...", "email": "...", "whatsapp": "..."},
  "testemunho": {"citacao": "...", "atribuicao": "..."},
  "precosHome": [{"servico": "...", "valor": "...", "nota": "..."}, ...],
  "precarioAreas": [{"titulo": "...", "valor": "...", "nota": "..."}, ...]
}
```

Se as tabelas estiverem vazias (não deveria acontecer fora de erro de seed), devolve arrays/objectos vazios — nunca 500. O site trata ausência de dados como "usar fallback estático".

## Admin (Blade)

Rota `/admin/conteudo`, guard `web` (sessão, login próprio — distinto do cookie Sanctum SPA usado pelo CRM/cliente), middleware role=admin (reaproveita enum `role` já existente em `users`).

Estilo visual: replica o mockup fornecido pelo utilizador (sidebar escura fixa, cards arredondados, paleta dark, accent azul nos botões primários) — aplicado só a esta página nesta fase; sidebar preparada para crescer mas só com este 1 item de navegação por agora. O mockup completo (dashboard/clientes/intervenções/agendamentos/etc.) fica de referência para o sub-projecto 5 (CRM SPA completo), não é construído agora.

Conteúdo da página: 3 cards — "Contacto" (3 campos), "Testemunho" (2 campos), "Preços" (tabela agrupada por secção, servico/titulo em texto fixo, valor+nota editáveis por linha). Botão "Guardar" por card, com validação server-side inline (telefone/email formato, campos obrigatórios) e feedback de sucesso sem perder o que já estava preenchido nos outros cards.

## Fluxo de dados no site público

1. Página relevante (home, `/precario`, `/contactos`) faz `fetch('/api/public/conteudo-site')` no mount, com timeout curto (~3s) e `try/catch`.
2. Sucesso → usa os dados da resposta.
3. Falha/timeout/API em baixo → usa silenciosamente as constantes actuais de `site.ts` (mantidas no código como fallback, não removidas) — visitante nunca vê erro nem página vazia.
4. Sem cache de browser nesta fase (fetch simples a cada visita de página); reconsiderar só se performance vier a ser problema.

## Testes

- Feature: `GET /api/public/conteudo-site` devolve shape correto com dados seedados.
- Feature: admin autenticado consegue `PUT`/gravar contacto, testemunho e valores de preço, e persistem.
- Feature: utilizador não-admin (tecnico/cliente) recebe 403 em `/admin/conteudo`.
- Manual (browser): site com API acessível mostra dados da DB nos 3 blocos; com fetch a falhar (ex. bloquear endpoint), mostra os valores estáticos de fallback sem quebrar layout.

## Fora de scope / decisões explícitas

- Sem CRUD de linhas de preço (só edição de valor/nota das linhas seedadas).
- Sem rebuild do site ao gravar — entrega é sempre via fetch client-side.
- Sem SPA/CRM novo — página Blade dentro da própria API app.
- `paginas.ts` fica inteiramente fora desta fase.
