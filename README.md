# laravel-cms

CMS construído de raiz em Laravel puro, seguindo o blueprint "GodLike" (ver `docs/blueprint/`).

O admin cria **tipos de conteúdo**, **páginas** e compõe **blocos** nas páginas — tudo como dados na DB.
O dev cria **blocos** (Blade components), **plugins** (ServiceProviders) e **models** — tudo como código no git.

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
| `cms:promote:type {slug}` | Promove um tipo de admin a Model+Migration+Resource (dev-time) |

## Regras de ouro

Ver `docs/blueprint/01-principios.md` (G1–G7). Resumo: nunca gerar código em runtime,
nunca EAV, boot nunca lê a DB, git é a autoridade, o admin cria dados e o dev cria código.
