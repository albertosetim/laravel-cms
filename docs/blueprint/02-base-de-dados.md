# 02 — Base de Dados (v2)

> Uma só base de dados. **PostgreSQL** — decisão fechada: `jsonb` + índices
> GIN são o que permite tipos criados pelo admin serem consultáveis sem DDL
> em runtime (G1) e sem EAV (G2). Em MySQL este desenho degrada (JSON sem
> GIN, generated columns exigiriam DDL por tipo); só aceitar MySQL se um
> requisito de hosting obrigar, e nesse caso documentar a perda.

## Tabelas

### `cms_pages` — nós da árvore
```
id                    bigint PK
translation_group_id  uuid              # liga as variantes de locale da "mesma" página
locale                string(10)        # 'de', 'en', 'it', ...
slug                  string            # único entre irmãos do mesmo parent+locale
name                  string            # nome interno na árvore
template              string            # layout Blade que envolve o body de blocos
parent_id             bigint nullable FK -> cms_pages.id
position              unsignedInteger
status                enum(draft,published)
published_revision_id bigint nullable FK -> cms_page_revisions.id   # o que o público vê
seo_title             string nullable
seo_description       text nullable
show_in_menu          boolean default true
timestamps, softDeletes
```
Índices: `(parent_id, position)`, `(parent_id, locale, slug)` único,
`(status)`, `(translation_group_id)`.

### `cms_page_revisions` — conteúdo da página, versionado
O body (árvore de blocos) **vive sempre aqui**; não há coluna `data` na
página. Editar = escrever na revision de rascunho; publicar = apontar
`published_revision_id` para um snapshot imutável. Draft-while-published
sai de graça.
```
id          bigint PK
page_id     bigint FK -> cms_pages.id (cascade)
data        jsonb             # árvore de blocos (ver 04-blocks.md)
is_draft    boolean           # exatamente uma draft "corrente" por página
created_by  bigint FK -> users.id
created_at  timestamp
```
Índices: `(page_id, is_draft)`, `(page_id, created_at)`.
Retenção: manter as últimas N revisions publicadas (config), podar por
comando agendado.

### `cms_types` — tipos criados pelo admin
```
id          bigint PK
slug        string unique     # 'team-member', 'faq'
name        string
icon        string nullable   # para o menu do backend
blueprint   jsonb             # campos + types, mesma forma dos blueprints de bloco
options     jsonb             # tem slug? tem árvore? ordenável? por locale?
timestamps
```

### `cms_entries` — registos dos tipos de admin
```
id                    bigint PK
type_id               bigint FK -> cms_types.id
translation_group_id  uuid nullable
locale                string(10) nullable
slug                  string nullable
position              unsignedInteger
status                enum(draft,published)
published_at          timestamp nullable
data                  jsonb             # valores conforme o blueprint do tipo
timestamps, softDeletes
```
Índices: `(type_id, status)`, `(type_id, slug, locale)` único onde slug
not null, e **GIN em `data`** (`jsonb_path_ops`) — é este índice que torna
"filtra membros da equipa por departamento" viável sem coluna nova e sem
EAV. Campos quentes promovem-se mais tarde (ver `05`).

### `cms_plugins` — catálogo de módulos
```
id, slug unique, name, version, enabled boolean default false, timestamps
```
Autoridade do estado ativo. O boot **não a lê** (G3) — lê o cache
materializado por `cms:plugins:sync` (ver `06`).

## O que NÃO existe

- Tabela de meta key/value (G2).
- Coluna `data` em `cms_pages` (o conteúdo vive nas revisions).
- Segunda connection/DB.
- Tabelas de blueprint de blocos: blueprints de blocos são **ficheiros**
  gerados por `cms:build` e committed (autoria dev), só os de tipos de
  admin vivem em `cms_types` (autoria admin) — G7.

## Spatie

Migrations publicadas pelos próprios packages (`laravel-permission`,
`laravel-medialibrary`, `laravel-activitylog`). Media anexa-se a models
reais (`Page`, `Entry`) via collections nomeadas pelo campo do blueprint
(ver `04` e `07`).

## Pronto quando

- `php artisan migrate:fresh` corre limpo; todas as tabelas e índices acima
  existem, incluindo o GIN em `cms_entries.data`.
- Zero queries à DB durante o boot (testar com a DB desligada: `artisan
  about` tem de correr).
- Um teste prova que filtrar `cms_entries` por um campo do jsonb usa o
  índice GIN (explain).
