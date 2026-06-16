# Laravel CMS

> A content management system built from scratch in plain Laravel — content types are
> designed in the admin and compiled into **real, typed code**, not stored as generic JSON.

<p>
  <img alt="Laravel" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.3+-777BB4?logo=php&logoColor=white">
  <img alt="Filament" src="https://img.shields.io/badge/Filament-5-F59E0B">
  <img alt="PostgreSQL" src="https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white">
  <img alt="Tests" src="https://img.shields.io/badge/tests-Pest-success">
</p>

## Overview

Define a **content type** in the admin designer (fields + relations) and the CMS generates
a real **Eloquent Model + Migration + Filament Resource** with a single click (or via
`cms:make:type`). Content lives in **typed tables**, never in a generic EAV/JSON blob.
Developers also author **blocks** (Blade components) and **plugins** (service providers).

The result is a CMS that behaves like a normal Laravel app: every content type is plain,
reviewable, version-controlled code you fully own.

> ⚠️ **Architecture note.** By product decision, the admin's *Generate code* button writes
> files and runs `migrate` in **any environment** — a deliberate departure from a strict
> no-runtime-codegen policy. Production implications: ephemeral or
> read-only filesystems (containers), multi-server setups (files land on a single node),
> DDL without rollback, and the next git deploy may overwrite generated files. **In
> production, prefer running `cms:make:type {slug}` in the pipeline and committing the
> result.**

## Features

**Content & structure**
- **Pages** with a tree hierarchy, draft/publish workflow, signed-URL preview, and a
  base **layout grid** (full width, 6+6, 8+4, 4+4+4, …) — each block picks its column on a
  12-column grid.
- **Menus** (name + slug + a two-level item tree), placeable on a page via the *Menu* block.
- **Content types** defined in the admin (stored as a JSON definition). Each type gets its **own
  sidebar entry** (under *Content*) with a filtered CRUD.

**Settings** (polymorphic, media-library style)
- A `HasSettings` trait — auto-injected into generated models and applied to core models —
  exposes per-type settings: `Blog::settings()->get('key')`.
- **General settings** page (site name, contact, timezone, maintenance toggle) with
  defaults and fallback read from `.env`.
- Per-type settings via a *Settings* button on each content listing.
- Maintenance mode is enforced on the public frontend; the admin stays reachable.

**Localization**
- Site locales and default are configured in `config/cms.php` (read by routing before any
  DB query). The public frontend serves one page row per locale (`translation_group_id`).
- The **admin panel UI is fully translatable**: strings use `__()` keys (English) with a
  German translation in `lang/de.json`. Navigation groups translate per request.
- **Per-user panel language**: users default to the site language and may pick another in
  their profile or have it set in the user form.

**Operations & access**
- **System logs**: a native Filament page unifying the activity log (Spatie) and the
  Laravel log files into one filterable table, with an IDE-style detail view.
- **Role-based access** (Spatie permission): navigation group *Permissions* (Roles +
  Groups); *System* tooling (content types, users, settings, logs) is admin-only.

## Tech stack

| Area        | Technology |
|-------------|------------|
| Framework   | Laravel 13 · PHP 8.3+ |
| Admin panel | Filament 5 · Livewire · Alpine.js · Tailwind CSS 4 |
| Database    | PostgreSQL 17 (JSONB + GIN for queryable content-type definitions) |
| Packages    | Spatie `laravel-permission`, `laravel-medialibrary`, `laravel-activitylog` |
| Tooling     | DDEV (local environment) · Pest (tests) |

## Getting started

Requires [DDEV](https://ddev.com).

```bash
ddev start
ddev exec composer install
ddev exec php artisan migrate
ddev exec php artisan db:seed --class=CmsSeeder   # roles, admin user, de/en homepages
ddev exec npm install && ddev exec npm run build  # or: npm run dev
```

- **App:** https://laravel-cms.ddev.site
- **Admin:** https://laravel-cms.ddev.site/admin
- **Dev login:** `admin@laravel-cms.test` / `password` — change outside development.

## Testing

Tests run against a **real PostgreSQL** database (`testing`) for JSONB/GIN parity — SQLite
does not cover the CMS:

```bash
ddev exec php artisan test
```

## Configuration

Application settings are editable in the admin (**System → Settings**); their initial
values and fallbacks come from `.env`:

| Variable | Default | Purpose |
|----------|---------|---------|
| `SETTINGS_SITE_NAME` | `web-crossing CMS` | Site name |
| `SETTINGS_CONTACT_EMAIL` | `info@web-crossing.com` | Contact email |
| `SETTINGS_CONTACT_PHONE` | `+43 512 206567` | Contact phone |
| `SETTINGS_TIMEZONE` | `Europe/Vienna` | Application timezone |
| `SETTINGS_MAINTENANCE_MODE` | `false` | Public maintenance mode |

Locales live in `config/cms.php` (`locales`, `default_locale`).

## CMS commands

| Command | Purpose |
|---------|---------|
| `cms:make:type {slug} [--migrate]` | Generate Model + Migration + Resource from a content type |
| `cms:build [--check]` | Extract block definitions to `resources/data/blocks.json` (committed) |
| `cms:plugins:sync` | Discover plugins, resolve dependencies, materialize the boot cache |
| `cms:plugins:enable {slug}` / `cms:plugins:disable {slug}` | Toggle a plugin (deploy-time) |

## Content types → generated code

1. In the admin, go to **System → Content types** and create a type: name, fields (text,
   rich text, number, date, image, selection, link, menu) and **relations** (`belongsTo`,
   `belongsToMany`, `hasMany`) to other types or core models.
2. Click **Generate code** (or run `cms:make:type {slug} --migrate`).
3. The CMS writes `app/Models/{Type}.php`, the migration (typed columns + FK/pivot) and
   `app/Filament/Resources/{Type}/…`. The resource appears under *Content* after a reload.
   From then on the developer owns the files — evolve the schema with new migrations.

## Conventions

A few principles the project holds to: boot never reads the database, git is the source of
truth, and there is no EAV — content always lives in typed tables. The one **deliberate
exception** is the admin's *Generate code* button, which performs code generation at
runtime (see the architecture note above).
