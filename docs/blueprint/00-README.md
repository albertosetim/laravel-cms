# CMS em Laravel — Blueprint v2 "GodLike"

> Documento-mãe da versão 2. Substitui o blueprint v1. A diferença de
> filosofia: a v1 era defensiva ("o que o admin não pode fazer"); a v2 dá
> **poder máximo ao admin** — criar tipos de conteúdo, criar páginas, compor
> componentes dentro das páginas — sem ceder um milímetro nas regras de ouro.
> O truque não é deixar o admin escrever código: é tornar os **dados** do
> admin tão capazes que ele nunca sinta falta do código.

## A promessa

**Para o admin:** cria um tipo "Equipa" com os campos que quiser, cria
páginas na árvore, e monta cada página arrastando blocos (hero, galeria,
texto, formulário, listagem de equipa) — tudo no backend, sem dev.

**Para o dev:** um bloco é um Blade component normal. Um plugin é um
ServiceProvider normal numa pasta. Um tipo "a sério" é um model Eloquent
normal. Não há DSL própria, não há parser próprio, não há runtime mágico.
Quem sabe Laravel sabe este CMS.

## A linha que torna isto possível (G7)

**O admin cria dados; o dev cria código.** Tudo o que o admin "cria" é uma
linha numa tabela — um tipo é um blueprint JSON, uma página é um nó da
árvore, um componente numa página é um item num documento JSON. Nada disso
gera ficheiros, nada disso corre `migrate`, nada disso toca no disco. O que
parece "o admin criou um model" é na verdade "o admin criou um blueprint e o
sistema serve-lhe um CRUD dinâmico". Quando um tipo de admin cresce ao ponto
de precisar de código real, há um **caminho de promoção** dev-time
(`cms:promote:type`) que o converte em model tipado committed.

## Os três poderes do admin

| Poder | O que é por baixo | Ficheiro |
|---|---|---|
| Criar **tipos** (models editoriais) | blueprint JSON em `cms_types` + entries em `cms_entries` (jsonb) + Filament dinâmico | `05-tipos-admin.md` |
| Criar **páginas** | nó em `cms_pages`, árvore relacional, routing por cadeia de slugs | `03-arvore-routing.md` |
| Compor **componentes nas páginas** | documento JSON de blocos; cada bloco = Blade component com blueprint extraído em build | `04-blocks.md` |

## O poder do dev

| Poder | O que é por baixo | Ficheiro |
|---|---|---|
| Criar **blocos** | Blade component + campos `x-cms`; aparece na paleta do builder | `04-blocks.md` |
| Criar **plugins** | pasta em `app/Plugins/` com Plugin.php (ServiceProvider) que serve N blocos, rotas, models, migrations | `06-plugins.md` |
| **Promover** tipos de admin a código | `cms:promote:type` emite Model + Migration + Resource dev-time | `05-tipos-admin.md` |

## Stack

- Laravel (LTS atual) — components, providers, casts, policies, cache. Nada reinventado.
- **PostgreSQL** (decisão tomada, ver `02`): `jsonb` + índice GIN é o que torna
  os tipos de admin consultáveis **sem** DDL em runtime e **sem** EAV.
- Filament para o backend (forms dinâmicos a partir de blueprints).
- Spatie: `laravel-permission`, `laravel-medialibrary`, `laravel-activitylog`.
- Blade puro no frontend; o sistema de blocos é a API de components do Blade.

## Ordem de construção

| # | Ponto | Ficheiro | Depende de |
|---|---|---|---|
| 1 | Princípios G1–G7 | `01-principios.md` | — |
| 2 | Base de dados | `02-base-de-dados.md` | 1 |
| 3 | Árvore, routing, locales, draft/publish | `03-arvore-routing.md` | 2 |
| 4 | Blocos e builder (`x-cms`) | `04-blocks.md` | 2, 3 |
| 5 | Tipos de admin + promoção | `05-tipos-admin.md` | 2, 4 |
| 6 | Plugins | `06-plugins.md` | 4 |
| 7 | Backend, edição, permissões | `07-backend-edicao.md` | 3, 4, 5 |

## Decisões fechadas nesta versão (eram pontos abertos/fracos na v1)

1. **Multilíngue: incluído desde o dia 1.** Linha por `(página, locale)`,
   `translation_group_id`, routing com prefixo de locale. Ver `03`.
2. **Draft-while-published: incluído.** Revisions + ponteiro de publicação.
   O editor edita rascunho com a versão publicada no ar. Ver `03` e `07`.
3. **Modo de edição: formulário/builder no backend (Filament).** Edição
   in-context fica fora do scope. O modo `edit` dos `x-cms` não existe na v2.
4. **Toggle de plugins: estado na DB, cache materializado por artisan.**
   O toggle no admin é opcional e só ativável em single-server com FS
   gravável (`cms.plugins.runtime_toggle`). Default: deploy-time. Ver `06`.
5. **Campos `x-cms` declaram-se incondicionalmente** (fora de `@if`/`@foreach`).
   Repetição é o type `repeater`, nunca `@foreach` de autor. Ver `04`.
6. **CI valida blueprints** em vez de os regenerar no deploy: o pipeline
   falha se `cms:build` produzir output diferente do committed (G5).

## Como usar com o Claude Code

Implementa por ordem da tabela. Cada ficheiro tem "Pronto quando" com
critérios de aceitação. Se uma decisão de implementação parecer contrariar
um princípio de `01-principios.md`, pára e sinaliza em vez de improvisar.
Código revisável: legível, testado, sem segredos hardcoded (env/secrets).
