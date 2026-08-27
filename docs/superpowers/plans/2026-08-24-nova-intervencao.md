# Nova Intervenção (sub-projecto 7) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin create a new ticket/intervenção directly from the CRM via a modal form, instead of needing Postman/manual API calls.

**Architecture:** Frontend-only (`rui-tech-helper-crm`). A Radix Dialog (`@radix-ui/react-dialog`, new dependency) wraps a form triggered by a "+ Nova Intervenção" button on `tickets-list.tsx`. Two new searchable/filterable combobox components (`ClienteCombobox`, `TecnicoCombobox`) handle cliente/técnico selection. Submission follows the codebase's existing convention (seen in `orcamento-form.tsx` and `ticket-detail.tsx`'s `handleAtribuir`) of a plain async handler + local `submitting`/error `useState` + `apiFetch` + `queryClient.invalidateQueries` — **not** `@tanstack/react-query`'s `useMutation`, which this codebase never uses despite having the dependency. No backend changes: `POST /api/admin/tickets`, `GET /api/admin/clientes?search=`, `GET /api/admin/tecnicos` already exist and already have full validation.

**Tech Stack:** React + Vite + TanStack Router + TanStack Query + Tailwind (frontend only, no test runner configured in this repo).

**Spec:** `docs/superpowers/specs/2026-08-24-nova-intervencao-design.md`

---

## Task 1: Install `@radix-ui/react-dialog`

**Files:**
- Modify: `package.json`, `package-lock.json` (or equivalent lockfile)

- [ ] **Step 1: Install the dependency**

Run (from `rui-tech-helper-crm/`):
```bash
npm install @radix-ui/react-dialog
```

- [ ] **Step 2: Verify it installed**

Run: `npm ls @radix-ui/react-dialog`
Expected: prints the installed version, no errors.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: add @radix-ui/react-dialog dependency"
```

---

## Task 2: `Dialog.tsx` wrapper

**Files:**
- Create: `src/components/ui/Dialog.tsx`

- [ ] **Step 1: Write the component**

```tsx
import * as DialogPrimitive from '@radix-ui/react-dialog';
import type { ReactNode } from 'react';

export const DialogRoot = DialogPrimitive.Root;
export const DialogTrigger = DialogPrimitive.Trigger;

export function DialogContent({ title, children }: { title: string; children: ReactNode }) {
  return (
    <DialogPrimitive.Portal>
      <DialogPrimitive.Overlay className="fixed inset-0 z-40 bg-background/80 backdrop-blur-sm" />
      <DialogPrimitive.Content className="panel-tech fixed left-1/2 top-1/2 z-50 w-[calc(100%-2rem)] max-w-lg -translate-x-1/2 -translate-y-1/2 p-6 focus:outline-none">
        <div className="mb-4 flex items-center justify-between">
          <DialogPrimitive.Title className="text-lg font-semibold text-foreground">{title}</DialogPrimitive.Title>
          <DialogPrimitive.Close
            aria-label="Fechar"
            className="flex size-11 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-secondary hover:text-foreground"
          >
            <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth={2} aria-hidden="true">
              <path strokeLinecap="round" strokeLinejoin="round" d="M6 6l12 12M18 6L6 18" />
            </svg>
          </DialogPrimitive.Close>
        </div>
        {children}
      </DialogPrimitive.Content>
    </DialogPrimitive.Portal>
  );
}
```

**Why these three exports, not a single component:** `DialogRoot`/`DialogTrigger` are re-exported as-is (Radix already gets these right — controlled `open`/`onOpenChange`, `asChild`, etc.). Only `Content` needs project-specific styling (the `panel-tech` panel look, the close button), so that's the only one wrapped.

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: builds successfully, no TypeScript errors (this file isn't imported anywhere yet, so it just needs to type-check standalone).

- [ ] **Step 3: Commit**

```bash
git add src/components/ui/Dialog.tsx
git commit -m "feat: add reusable Dialog wrapper over @radix-ui/react-dialog"
```

---

## Task 3: `ClienteCombobox.tsx`

**Files:**
- Create: `src/components/ClienteCombobox.tsx`

- [ ] **Step 1: Write the component**

```tsx
import { useEffect, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';

type ClienteOption = { id: number; nome: string };
type ClientesSearchResponse = { data: ClienteOption[] };

const INPUT_CLASS =
  'w-full rounded-md border border-input bg-secondary px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-electric-soft';

export function ClienteCombobox({
  value,
  onChange,
}: {
  value: ClienteOption | null;
  onChange: (cliente: ClienteOption | null) => void;
}) {
  const [inputValue, setInputValue] = useState(value?.nome ?? '');
  const [debounced, setDebounced] = useState('');
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const listboxId = 'cliente-combobox-listbox';

  useEffect(() => {
    const timer = setTimeout(() => setDebounced(inputValue), 300);
    return () => clearTimeout(timer);
  }, [inputValue]);

  const { data } = useQuery({
    queryKey: ['clientes-search', debounced],
    queryFn: () => apiFetch<ClientesSearchResponse>(`/api/admin/clientes?search=${encodeURIComponent(debounced)}`),
    enabled: debounced.trim().length >= 2,
  });

  const options = data?.data ?? [];

  function selectOption(option: ClienteOption) {
    onChange(option);
    setInputValue(option.nome);
    setOpen(false);
    setActiveIndex(-1);
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
    if (!open || options.length === 0) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveIndex((i) => (i + 1) % options.length);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveIndex((i) => (i - 1 + options.length) % options.length);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      selectOption(options[activeIndex]);
    } else if (e.key === 'Escape') {
      setOpen(false);
    }
  }

  return (
    <div className="relative">
      <label htmlFor="cliente-combobox-input" className="mb-1 block text-sm font-medium text-foreground">
        Cliente
      </label>
      <input
        id="cliente-combobox-input"
        role="combobox"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-activedescendant={activeIndex >= 0 ? `cliente-option-${options[activeIndex].id}` : undefined}
        autoComplete="off"
        value={inputValue}
        onChange={(e) => {
          setInputValue(e.target.value);
          setOpen(true);
          onChange(null);
          setActiveIndex(-1);
        }}
        onKeyDown={handleKeyDown}
        onFocus={() => inputValue && setOpen(true)}
        placeholder="Escrever nome, email ou telefone..."
        className={INPUT_CLASS}
      />
      {open && options.length > 0 && (
        <ul
          id={listboxId}
          role="listbox"
          className="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-secondary shadow-lg"
        >
          {options.map((option, index) => (
            <li
              key={option.id}
              id={`cliente-option-${option.id}`}
              role="option"
              aria-selected={index === activeIndex}
              onMouseDown={(e) => {
                e.preventDefault();
                selectOption(option);
              }}
              className={`cursor-pointer px-3 py-2 text-sm ${
                index === activeIndex ? 'bg-electric/15 text-electric-soft' : 'text-foreground/80 hover:bg-secondary/70'
              }`}
            >
              {option.nome}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
```

Note: `/api/admin/clientes` returns `{ id, nome, email, telefone, intervencoes_count }` per `clientes-list.tsx`'s `ClienteRow` type — `ClienteOption` here only declares the two fields this component needs; TypeScript structural typing accepts the superset response fine.

`onMouseDown` (not `onClick`) with `preventDefault()` on the options is required — otherwise the input's `onBlur`-driven close would fire before the click registers and the option would never be selected.

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: builds successfully, no TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add src/components/ClienteCombobox.tsx
git commit -m "feat: add searchable ClienteCombobox with ARIA combobox pattern"
```

---

## Task 4: `TecnicoCombobox.tsx`

**Files:**
- Create: `src/components/TecnicoCombobox.tsx`

- [ ] **Step 1: Write the component**

```tsx
import { useMemo, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { apiFetch } from '@/lib/apiClient';

type Tecnico = { id: number; name: string };
type TecnicosResponse = { tecnicos: Tecnico[] };

const INPUT_CLASS =
  'w-full rounded-md border border-input bg-secondary px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-electric-soft';
const SEM_TECNICO_ID = 'tecnico-option-sem-tecnico';

export function TecnicoCombobox({
  value,
  onChange,
}: {
  value: Tecnico | null;
  onChange: (tecnico: Tecnico | null) => void;
}) {
  const [inputValue, setInputValue] = useState(value?.name ?? '');
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const listboxId = 'tecnico-combobox-listbox';

  const { data } = useQuery({
    queryKey: ['tecnicos'],
    queryFn: () => apiFetch<TecnicosResponse>('/api/admin/tecnicos'),
  });

  const tecnicos = data?.tecnicos ?? [];
  const filtered = useMemo(
    () => tecnicos.filter((t) => t.name.toLowerCase().includes(inputValue.toLowerCase())),
    [tecnicos, inputValue],
  );

  function selectTecnico(tecnico: Tecnico | null) {
    onChange(tecnico);
    setInputValue(tecnico?.name ?? '');
    setOpen(false);
    setActiveIndex(-1);
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>) {
    if (!open) return;
    const count = filtered.length + 1; // +1 for "Sem técnico atribuído"
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActiveIndex((i) => (i + 1) % count);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActiveIndex((i) => (i - 1 + count) % count);
    } else if (e.key === 'Enter' && activeIndex >= 0) {
      e.preventDefault();
      if (activeIndex === 0) selectTecnico(null);
      else selectTecnico(filtered[activeIndex - 1]);
    } else if (e.key === 'Escape') {
      setOpen(false);
    }
  }

  return (
    <div className="relative">
      <label htmlFor="tecnico-combobox-input" className="mb-1 block text-sm font-medium text-foreground">
        Técnico (opcional)
      </label>
      <input
        id="tecnico-combobox-input"
        role="combobox"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-activedescendant={
          activeIndex === 0 ? SEM_TECNICO_ID : activeIndex > 0 ? `tecnico-option-${filtered[activeIndex - 1].id}` : undefined
        }
        autoComplete="off"
        value={inputValue}
        onChange={(e) => {
          setInputValue(e.target.value);
          setOpen(true);
          setActiveIndex(-1);
        }}
        onKeyDown={handleKeyDown}
        onFocus={() => setOpen(true)}
        placeholder="Escrever nome do técnico..."
        className={INPUT_CLASS}
      />
      {open && (
        <ul
          id={listboxId}
          role="listbox"
          className="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-md border border-border bg-secondary shadow-lg"
        >
          <li
            id={SEM_TECNICO_ID}
            role="option"
            aria-selected={activeIndex === 0}
            onMouseDown={(e) => {
              e.preventDefault();
              selectTecnico(null);
            }}
            className={`cursor-pointer px-3 py-2 text-sm ${
              activeIndex === 0 ? 'bg-electric/15 text-electric-soft' : 'text-muted-foreground hover:bg-secondary/70'
            }`}
          >
            Sem técnico atribuído
          </li>
          {filtered.map((tecnico, index) => (
            <li
              key={tecnico.id}
              id={`tecnico-option-${tecnico.id}`}
              role="option"
              aria-selected={index + 1 === activeIndex}
              onMouseDown={(e) => {
                e.preventDefault();
                selectTecnico(tecnico);
              }}
              className={`cursor-pointer px-3 py-2 text-sm ${
                index + 1 === activeIndex ? 'bg-electric/15 text-electric-soft' : 'text-foreground/80 hover:bg-secondary/70'
              }`}
            >
              {tecnico.name}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
```

`GET /api/admin/tecnicos` returns `{ tecnicos: [{ id, name }] }` — confirmed by the existing `Tecnico` type and call site in `ticket-detail.tsx`.

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: builds successfully, no TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add src/components/TecnicoCombobox.tsx
git commit -m "feat: add TecnicoCombobox with optional 'sem técnico' option"
```

---

## Task 5: `NovaIntervencaoForm.tsx`

**Files:**
- Create: `src/components/NovaIntervencaoForm.tsx`

- [ ] **Step 1: Write the component**

```tsx
import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { apiFetch, ApiError } from '@/lib/apiClient';
import { ClienteCombobox } from './ClienteCombobox';
import { TecnicoCombobox } from './TecnicoCombobox';

type Cliente = { id: number; nome: string };
type Tecnico = { id: number; name: string };
type FieldErrors = Record<string, string[]>;

const CATEGORIAS = ['hardware', 'software', 'rede', 'backup'] as const;
const PRIORIDADES = ['urgente', 'normal', 'baixa'] as const;

const INPUT_CLASS =
  'w-full rounded-md border border-input bg-secondary px-3 py-2 text-sm text-foreground outline-none focus-visible:ring-2 focus-visible:ring-electric-soft';
const LABEL_CLASS = 'mb-1 block text-sm font-medium text-foreground';

export function NovaIntervencaoForm({ onCreated }: { onCreated: () => void }) {
  const queryClient = useQueryClient();
  const [cliente, setCliente] = useState<Cliente | null>(null);
  const [tecnico, setTecnico] = useState<Tecnico | null>(null);
  const [categoria, setCategoria] = useState('');
  const [prioridade, setPrioridade] = useState('');
  const [titulo, setTitulo] = useState('');
  const [descricao, setDescricao] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [genericError, setGenericError] = useState<string | null>(null);

  const canSubmit = cliente !== null && categoria !== '' && prioridade !== '' && titulo.trim() !== '' && descricao.trim() !== '';

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!canSubmit || submitting) return;

    setSubmitting(true);
    setFieldErrors({});
    setGenericError(null);

    try {
      await apiFetch('/api/admin/tickets', {
        method: 'POST',
        body: {
          cliente_id: cliente!.id,
          tecnico_id: tecnico?.id ?? null,
          categoria,
          prioridade,
          titulo,
          descricao,
        },
      });
      queryClient.invalidateQueries({ queryKey: ['tickets'] });
      onCreated();
    } catch (err) {
      if (err instanceof ApiError && err.status === 422) {
        const body = err.body as { errors?: FieldErrors };
        setFieldErrors(body.errors ?? {});
      } else {
        setGenericError('Erro ao criar intervenção. Tenta novamente.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={handleSubmit} className="flex flex-col gap-4">
      {genericError && (
        <p role="alert" className="text-sm text-destructive">
          {genericError}
        </p>
      )}

      <div>
        <ClienteCombobox value={cliente} onChange={setCliente} />
        {fieldErrors.cliente_id && (
          <p role="alert" className="mt-1 text-xs text-destructive">
            {fieldErrors.cliente_id[0]}
          </p>
        )}
      </div>

      <div>
        <TecnicoCombobox value={tecnico} onChange={setTecnico} />
        {fieldErrors.tecnico_id && (
          <p role="alert" className="mt-1 text-xs text-destructive">
            {fieldErrors.tecnico_id[0]}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="nova-intervencao-categoria" className={LABEL_CLASS}>
          Categoria
        </label>
        <select
          id="nova-intervencao-categoria"
          value={categoria}
          onChange={(e) => setCategoria(e.target.value)}
          className={INPUT_CLASS}
        >
          <option value="">Selecionar categoria</option>
          {CATEGORIAS.map((c) => (
            <option key={c} value={c}>
              {c}
            </option>
          ))}
        </select>
        {fieldErrors.categoria && (
          <p role="alert" className="mt-1 text-xs text-destructive">
            {fieldErrors.categoria[0]}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="nova-intervencao-prioridade" className={LABEL_CLASS}>
          Prioridade
        </label>
        <select
          id="nova-intervencao-prioridade"
          value={prioridade}
          onChange={(e) => setPrioridade(e.target.value)}
          className={INPUT_CLASS}
        >
          <option value="">Selecionar prioridade</option>
          {PRIORIDADES.map((p) => (
            <option key={p} value={p}>
              {p}
            </option>
          ))}
        </select>
        {fieldErrors.prioridade && (
          <p role="alert" className="mt-1 text-xs text-destructive">
            {fieldErrors.prioridade[0]}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="nova-intervencao-titulo" className={LABEL_CLASS}>
          Título
        </label>
        <input
          id="nova-intervencao-titulo"
          type="text"
          maxLength={255}
          value={titulo}
          onChange={(e) => setTitulo(e.target.value)}
          className={INPUT_CLASS}
        />
        {fieldErrors.titulo && (
          <p role="alert" className="mt-1 text-xs text-destructive">
            {fieldErrors.titulo[0]}
          </p>
        )}
      </div>

      <div>
        <label htmlFor="nova-intervencao-descricao" className={LABEL_CLASS}>
          Descrição
        </label>
        <textarea
          id="nova-intervencao-descricao"
          rows={4}
          value={descricao}
          onChange={(e) => setDescricao(e.target.value)}
          className={INPUT_CLASS}
        />
        {fieldErrors.descricao && (
          <p role="alert" className="mt-1 text-xs text-destructive">
            {fieldErrors.descricao[0]}
          </p>
        )}
      </div>

      <button
        type="submit"
        disabled={!canSubmit || submitting}
        className="mt-2 flex h-11 cursor-pointer items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {submitting ? 'A criar...' : 'Criar intervenção'}
      </button>
    </form>
  );
}
```

`ApiError` is exported from `src/lib/apiClient.ts` (`export class ApiError extends Error { constructor(public status: number, public body: unknown) ... }`) — already used as the thrown error type for any non-2xx response, so `err instanceof ApiError && err.status === 422` is the correct check.

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: builds successfully, no TypeScript errors.

- [ ] **Step 3: Commit**

```bash
git add src/components/NovaIntervencaoForm.tsx
git commit -m "feat: add NovaIntervencaoForm with 422 field-error mapping"
```

---

## Task 6: Wire "Nova Intervenção" into `tickets-list.tsx`

**Files:**
- Modify: `src/routes/tickets-list.tsx`

- [ ] **Step 1: Add imports and dialog state**

Add to the top imports (after the existing `useAuth` import):

```tsx
import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { apiFetch } from '@/lib/apiClient';
import { useAuth } from '@/lib/auth';
import { TableSkeleton, EmptyState } from '@/components/table/TableParts';
import { DialogRoot, DialogTrigger, DialogContent } from '@/components/ui/Dialog';
import { NovaIntervencaoForm } from '@/components/NovaIntervencaoForm';
```

Inside `TicketsListPage`, right after the existing `const { user } = useAuth();` line, add:

```tsx
  const [dialogOpen, setDialogOpen] = useState(false);
```

- [ ] **Step 2: Replace the page heading with a heading + trigger row**

Replace this line:

```tsx
      <h1 className="mb-6 text-2xl font-bold text-foreground">Tickets</h1>
```

With:

```tsx
      <div className="mb-6 flex items-center justify-between">
        <h1 className="text-2xl font-bold text-foreground">Tickets</h1>
        {user?.role === 'admin' && (
          <DialogRoot open={dialogOpen} onOpenChange={setDialogOpen}>
            <DialogTrigger className="flex h-11 cursor-pointer items-center justify-center rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90">
              + Nova Intervenção
            </DialogTrigger>
            <DialogContent title="Nova Intervenção">
              <NovaIntervencaoForm onCreated={() => setDialogOpen(false)} />
            </DialogContent>
          </DialogRoot>
        )}
      </div>
```

Only admin gets the button — técnico role uses `/api/tecnico/tickets` (read/assign only, no `store` endpoint for técnico per `routes/api.php`), so the create action is admin-only by design, matching the existing `user?.role === 'admin'` gate pattern already used elsewhere in this file's sibling pages (`ticket-detail.tsx`'s "Atribuir técnico" section).

- [ ] **Step 3: Verify build**

Run: `npm run build`
Expected: builds successfully, no TypeScript errors.

- [ ] **Step 4: Commit**

```bash
git add src/routes/tickets-list.tsx
git commit -m "feat: wire Nova Intervenção dialog into tickets list"
```

---

## Task 7: Manual verification + final commit

**Files:** none (verification only)

- [ ] **Step 1: Full frontend build**

Run: `npm run build`
Expected: builds successfully, 0 TypeScript errors.

- [ ] **Step 2: Manual browser walkthrough** (via Playwright MCP or `npm run dev` + manual browser)

Log in as admin, navigate to `/tickets`, then:
1. Click "+ Nova Intervenção" → dialog opens, focus lands inside the dialog (Radix default), background is dimmed/blurred.
2. Press `Escape` → dialog closes.
3. Reopen. Type 2+ characters into the Cliente field → dropdown appears with matching clientes after the 300ms debounce.
4. Select a cliente via mouse click → input shows the cliente's name, dropdown closes.
5. Reopen combobox by typing again, use ArrowDown/ArrowUp/Enter to select via keyboard.
6. Técnico field: click it → shows "Sem técnico atribuído" plus the full técnico list without typing anything; type part of a name → list filters client-side.
7. Fill Categoria, Prioridade, Título, Descrição. Confirm the submit button is disabled until all required fields are filled, then becomes enabled.
8. Submit → button shows "A criar...", becomes disabled; on success the dialog closes and the tickets table refreshes to show the new ticket without a manual page reload.
9. Reopen the dialog, fill only Cliente + Categoria + Prioridade, leave Título/Descrição blank — confirm submit stays disabled (client-side guard).
10. To test the 422 path: temporarily pick a técnico, then use browser devtools or a second admin tab to soft-delete that técnico's user row (or simpler: submit with a cliente that doesn't exist by manipulating network request) — confirm a field-level error message with `role="alert"` appears under the relevant field and the dialog stays open. If this is impractical to trigger manually, accept the code-path is confirmed by the field-error rendering already being present and skip to Step 3 — note this in the task's completion notes.
11. Click the X close button — confirm it has a visible focus ring when tabbed to, and an `aria-label="Fechar"` (inspect via devtools accessibility tree).

- [ ] **Step 3: Commit any leftover verification notes**

If Step 2 required no code changes, there is nothing to commit — the branch is done. If any bug surfaced during manual verification, fix it, re-run `npm run build`, then:

```bash
git add -A
git commit -m "fix: address manual verification findings for Nova Intervenção"
```
