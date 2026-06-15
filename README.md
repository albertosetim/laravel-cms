# laravel-cms

CMS construído de raiz em Laravel puro, seguindo o blueprint "GodLike" (ver `docs/blueprint/`).

Define um **content type** no designer (campos + relações) e gera **Model + Migration +
FilamentResource reais** com um clique (ou `cms:make:type`). O conteúdo vive em tabelas
tipadas, não em jsonb genérico. O dev cria também **blocos** (Blade components) e
**plugins** (ServiceProviders).

> ⚠️ **Nota de arquitetura:** por decisão de produto, o botão "Gerar código" no admin
> escreve ficheiros e corre `migrate` em **qualquer ambiente** — isto contraria a regra
> G1 do blueprint original (zero codegen em runtime). Implicações em produção: filesystem
> efémero/read-only (containers), multi-servidor (ficheiros só num nó), DDL sem rollback,
> e o próximo deploy do git pode sobrepor-se ao gerado. Em produção, prefere o comando
> `cms:make:type {slug}` no pipeline e commita o resultado.

## Funcionalidades do backend

- **Páginas** com árvore, draft/publish, preview assinado e **layout base** (largura
  total, 6+6, 8+4, 4+4+4, etc.) — cada bloco escolhe a sua coluna na grelha de 12.
- **Tipos de conteúdo** definidos pelo admin (blueprint jsonb). Cada tipo ganha o
  **seu próprio item no menu lateral** (grupo *Conteúdo*) com o CRUD filtrado.
- **Menus** estilo Shopify (nome + slug + árvore de itens com 2 níveis), colocáveis
  numa página através do bloco *Menu*.
- **System** (só admins): Tipos de conteúdo, Utilizadores, Roles, Grupos.
- Grupos de navegação: *Conteúdo* · *Estrutura* (Páginas, Menus) · *System*.

## Stack

- Laravel 13 · PHP 8.4
- Filament 5 (painel admin) · Livewire · Alpine.js · Tailwind CSS 4
- PostgreSQL 17 (jsonb + GIN para tipos de admin consultáveis)
- Spatie: laravel-permission, laravel-medialibrary, laravel-activitylog
- DDEV para o ambiente local

## Desenvolvimento

```bash
ddev start
ddev exec php artisan migrate
ddev exec php artisan db:seed --class=CmsSeeder   # roles, admin user, homepages de/en
ddev exec npm run build                            # ou npm run dev
```

App: https://laravel-cms.ddev.site · Admin: https://laravel-cms.ddev.site/admin
Login dev: `admin@laravel-cms.test` / `password` (alterar fora de dev).

## Testes

Correm contra Postgres real (DB `testing`, paridade jsonb/GIN — sqlite não cobre o CMS):

```bash
ddev exec php artisan test
```

## Comandos do CMS

| Comando | Função |
|---|---|
| `cms:build` | Extrai blueprints dos blocos para `resources/data/blocks.json` (committed) |
| `cms:plugins:sync` | Descobre plugins, resolve dependências, materializa o cache de boot |
| `cms:plugins:enable {slug}` / `disable {slug}` | Ativa/desativa plugin (deploy-time) |
| `cms:make:type {slug} [--migrate]` | Gera Model + Migration + Resource a partir de um content type (designer) |

## Content types → models gerados

1. No admin, **System → Tipos de conteúdo**, cria um tipo: nome, campos (texto, rich
   text, número, data, imagem, seleção, link, menu) e **relações** (belongsTo,
   belongsToMany, hasMany) para outros tipos ou models do core.
2. Clica **Gerar código** (ou corre `cms:make:type {slug} --migrate`).
3. São escritos `app/Models/{Tipo}.php`, a migration (colunas tipadas + FK/pivot) e
   `app/Filament/Resources/{Tipo}/...`. O resource aparece no grupo *Conteúdo* após
   recarregar. A partir daí o dev é dono dos ficheiros (evolução = nova migration).

## Regras de ouro

Ver `docs/blueprint/01-principios.md` (G1–G7). O blueprint original proíbe codegen em
runtime (G1); este projeto **abre uma exceção deliberada**: o botão "Gerar código" no
admin gera ficheiros em qualquer ambiente (ver nota no topo). As restantes regras
mantêm-se: boot nunca lê a DB, git é a autoridade, nada de EAV.
