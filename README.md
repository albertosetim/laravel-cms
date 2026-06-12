# laravel-cms

CMS construído de raiz em Laravel puro, seguindo o blueprint "GodLike" (ver `docs/blueprint/`).

O admin cria **tipos de conteúdo**, **páginas** e compõe **blocos** nas páginas — tudo como dados na DB.
O dev cria **blocos** (Blade components), **plugins** (ServiceProviders) e **models** — tudo como código no git.

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
ddev exec npm run dev
```

App: https://laravel-cms.ddev.site · Admin: https://laravel-cms.ddev.site/admin

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
