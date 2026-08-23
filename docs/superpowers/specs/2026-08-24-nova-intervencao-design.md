# Nova Intervenção — Design

**Sub-projecto 7** do rebuild do CRM "O Rui dos Computadores". Sequência: 6 (Clientes+Dashboard, done) → **7 (Nova Intervenção)** → 8 (Agendamentos) → 9 (Equipamentos+Documentos) → 10 (Faturas+Comunicações+Relatórios+Definições).

## Goal

Admin consegue criar uma nova intervenção (ticket) directamente do CRM, sem precisar de API manual/Postman. Modal acessível com combobox de cliente pesquisável e atribuição opcional de técnico.

## Scope

Só frontend (`rui-tech-helper-crm`). Backend já tem tudo o necessário:
- `POST /api/admin/tickets` (`TicketController::store`) — aceita `cliente_id`, `tecnico_id` (nullable), `categoria`, `prioridade`, `titulo`, `descricao`.
- `GET /api/admin/clientes?search=` (sub-projecto 6) — lista/pesquisa clientes.
- `GET /api/admin/tecnicos` (`Admin\TecnicoController::index`) — lista técnicos.

Zero migration, zero controller novo, zero rota nova.

Agendamentos (calendário, nova entidade de dados) fica fora — vira sub-projecto 8 à parte, por não ter nenhuma tabela/model ainda.

## Architecture

Modal Radix Dialog (`@radix-ui/react-dialog`, nova dependência) disparado por botão "Nova Intervenção" em `tickets-list.tsx`. Form dentro do modal, submissão via TanStack Query `useMutation`, invalida a query `['tickets', ...]` no sucesso e fecha o modal.

## Components

### `src/components/ui/Dialog.tsx` (novo, reutilizável)

Wrapper fino sobre `@radix-ui/react-dialog`: `Dialog.Root`, `Dialog.Trigger`, `Dialog.Portal` + `Dialog.Overlay` (fundo escurecido, `bg-background/80 backdrop-blur-sm`) + `Dialog.Content` (`panel-tech`, `max-w-lg`, centrado), `Dialog.Title`, `Dialog.Close` (botão X no canto, `aria-label="Fechar"`). Radix trata focus-trap, ESC, `aria-modal`, scroll-lock do body automaticamente. Este é o único wrapper de modal do projecto — futuros modais (sub-projectos 8-10) reusam-no.

### `src/components/ClienteCombobox.tsx` (novo)

Combobox pesquisável controlado (`value: number | null`, `onChange`). Input de texto com debounce de 300ms dispara `useQuery(['clientes-search', termo], () => apiFetch('/api/admin/clientes?search=' + termo))` só quando o termo tem 2+ caracteres. Lista de resultados em dropdown (`role="listbox"`, cada opção `role="option"`), navegável por teclado (setas + Enter, Radix não cobre isto — implementação manual mínima com `useState` para índice activo). Ao seleccionar, mostra nome do cliente no input e fecha a lista.

### `src/components/TecnicoCombobox.tsx` (novo)

Mesma UI que `ClienteCombobox` mas sem debounce/search — carrega lista completa uma vez (`useQuery(['tecnicos'], ...)`, lista de técnicos costuma ser pequena) e filtra client-side conforme o utilizador escreve. Campo opcional: tem opção "Sem técnico atribuído" que limpa a selecção.

### Form "Nova Intervenção" (dentro do `Dialog.Content`, em `tickets-list.tsx` ou extraído para `src/components/NovaIntervencaoForm.tsx` se `tickets-list.tsx` ficar grande)

Campos, por ordem:
1. Cliente (obrigatório) — `ClienteCombobox`
2. Técnico (opcional) — `TecnicoCombobox`
3. Categoria (obrigatório) — `<select>` com `hardware/software/rede/backup`, mesmas opções já usadas em `tickets-list.tsx`
4. Prioridade (obrigatório) — `<select>` com `urgente/normal/baixa`
5. Título (obrigatório) — `<input type="text">`, max 255
6. Descrição (obrigatório) — `<textarea>`

Cada campo tem `<label htmlFor>` associado. Validação client-side mínima: desabilita o submit enquanto cliente/categoria/prioridade/título/descrição não estiverem preenchidos (não duplica as regras do backend, só evita round-trip óbvio). Erros de validação vindos do backend (422) mostrados por campo, mapeando a chave do erro Laravel (`errors.cliente_id`, etc.) para o campo correspondente; erro genérico não mapeado mostrado no topo do form com `role="alert"`.

Submit: `useMutation({ mutationFn: () => apiFetch('/api/admin/tickets', { method: 'POST', body: ... }) })`. `onSuccess`: `queryClient.invalidateQueries({ queryKey: ['tickets'] })`, fecha modal, reset form state, mostra toast de sucesso (usar padrão de feedback já existente no projecto — verificar em `pagamentos-list.tsx`/`ticket-detail.tsx` se há toast; se não houver nenhum, mensagem inline temporária substitui, sem introduzir lib de toast nova para uma única acção). Botão de submit mostra estado "A criar..." e fica desabilitado durante a mutação (evita duplo-submit).

## Data flow

```
Utilizador clica "Nova Intervenção"
  → Dialog abre, foco vai para input de Cliente
  → Utilizador escreve nome → debounce 300ms → GET /api/admin/clientes?search=...
  → Selecciona cliente da lista
  → (opcional) escreve/selecciona técnico
  → Preenche categoria/prioridade/título/descrição
  → Submit → POST /api/admin/tickets
  → Sucesso: invalida cache de tickets, fecha modal, lista actualiza sozinha (TanStack Query refetch)
  → Erro 422: mostra erros por campo, mantém modal aberto
  → Erro outro (rede/500): mensagem genérica no topo do form
```

## Accessibility

- Dialog: Radix garante `aria-modal="true"`, focus-trap, fecha com ESC, devolve foco ao trigger ao fechar.
- Todos os inputs têm `<label>` associado via `htmlFor`/`id`.
- Comboboxes seguem padrão ARIA combobox (`role="combobox"`, `aria-expanded`, `aria-controls` a apontar para a listbox, `aria-activedescendant` a acompanhar a opção destacada por teclado).
- Botão de fechar do modal tem `aria-label="Fechar"` (ícone sem texto visível).
- Mensagens de erro usam `role="alert"` para serem anunciadas por screen readers.
- Touch targets (botões, opções da lista) ≥44×44px.

## Testing

Sem backend novo → sem testes Pest adicionais (a suite de 140 testes já cobre `TicketController::store`). Frontend não tem test runner configurado neste repo — verificação será manual via Playwright MCP (fluxo completo: abrir modal, pesquisar cliente, atribuir técnico, submeter, confirmar que a lista actualiza e o modal fecha; depois testar caso de erro 422 com campo em falta).

## Out of scope (explicitamente)

- Edição de ticket existente (já existe via `ticket-detail.tsx`).
- Criação de ticket pelo próprio cliente no CRM interno (esse fluxo já existe no site público via `storeCliente`).
- Agendamentos/calendário — sub-projecto 8.
- Toast/notification system genérico — só resolvido ad-hoc se não existir nada reutilizável; não é objectivo deste sub-projecto introduzir uma lib de toast.
