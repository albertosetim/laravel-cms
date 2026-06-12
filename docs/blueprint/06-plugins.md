# 06 — Plugins (v2)

> Um plugin é um ServiceProvider numa pasta que serve **N blade components
> (blocos)**, mais rotas, models, migrations e itens de backend. First-party:
> código da agência, deployado por git, nunca upload em runtime. A v2 corrige
> a contradição da v1 no toggle (G1).

## O que um plugin fornece

| Artefacto | Como | Efeito |
|---|---|---|
| **Blocos** (o principal) | `resources/views/blocks/*.blade.php` | entram na paleta do builder após `cms:build` |
| Views/partials | `resources/views/` | namespace `{slug}::` |
| Rotas | `routes.php` | registadas antes do catch-all do CMS |
| Models + migrations | `Models/`, `database/migrations/` | domain models do plugin |
| Backend | `Filament/` | Resources/Pages registadas no painel |
| Config | `config.php` | merge em `cms.plugins.{slug}` |

## Layout

```
app/Plugins/
  Blog/
    Plugin.php
    routes.php
    config.php
    Models/Post.php
    Filament/Resources/PostResource.php
    database/migrations/
    resources/views/
      blocks/
        post-list.blade.php     # bloco "blog.post-list"
        post-teaser.blade.php   # bloco "blog.post-teaser"
      partials/
```

## A base `Plugin` — herdar o ciclo de vida, não recriá-lo

```php
abstract class Plugin extends ServiceProvider
{
    abstract public function slug(): string;
    abstract public function name(): string;
    public function version(): string { return '1.0.0'; }
    public function dependsOn(): array { return []; }   // slugs de outros plugins

    public function boot(): void
    {
        $dir = dirname((new \ReflectionClass($this))->getFileName());
        if (is_dir("$dir/database/migrations")) $this->loadMigrationsFrom("$dir/database/migrations");
        if (is_dir("$dir/resources/views"))     $this->loadViewsFrom("$dir/resources/views", $this->slug());
        if (is_dir("$dir/resources/views/blocks"))
            Blade::componentNamespace(/* blocos do plugin */, 'cms-'.$this->slug());
        if (file_exists("$dir/routes.php"))      $this->loadRoutesFrom("$dir/routes.php");
        if (file_exists("$dir/config.php"))      $this->mergeConfigFrom("$dir/config.php", 'cms.plugins.'.$this->slug());
    }
}
```
Convenção sobre configuração: largar ficheiros nas pastas certas chega.
Blocos de plugin ganham prefixo `{slug}.` no manifesto (`blog.post-list`)
— sem colisões com blocos core.

## Estado vs boot (G3 + G1, resolvido)

- **`cms_plugins` (DB):** catálogo + `enabled`. Autoridade do estado.
- **`bootstrap/cache/cms-plugins.php` (ficheiro):** lista ordenada
  (dependências resolvidas) dos providers ativos. Gerado por artisan,
  nunca à mão, **nunca por request web**.

O boot lê só o ficheiro; se não existir, regista zero plugins e a app
arranca na mesma.

### Comandos

- `cms:plugins:sync` — varre `app/Plugins/*/Plugin.php`, upsert em
  `cms_plugins` (novos entram **desativados**), resolve a ordem por
  `dependsOn()` (falha em ciclos), reescreve o cache a partir dos ativos.
  Corre **no deploy** (pipeline) e em dev.
- `cms:plugins:enable {slug}` / `disable {slug}` — mudam `enabled` e
  chamam `sync`. Disable recusa se outro plugin ativo depender dele.

### O toggle no admin (a correção da v1)

O fluxo default é **deploy-time**: o admin vê o estado dos plugins no
backend, mas alterar exige `enable/disable` + deploy (ou um `sync` em todos
os nós). Isto respeita G1 em qualquer topologia.

Exceção explícita e documentada: `cms.plugins.runtime_toggle = true`
(config) ativa o toggle no admin para instalações **single-server com
filesystem gravável**. O toggle muda a DB e despacha o `sync` via
`Artisan::queue` — assíncrono, auditado no activity log. Em multi-servidor
ou FS read-only esta flag fica `false` e a UI mostra o toggle desativado
com a razão. Não há terceiro modo.

Desativar = sair do cache → provider não regista → rotas, views e blocos
desaparecem (blocos do plugin saem da paleta no próximo `cms:build`).
Migrations e dados **ficam** — desativar nunca destrói tabelas.

## Blocos órfãos em páginas

Se uma página publicada usa `blog.post-list` e o plugin Blog é desativado,
o render salta a instância e regista um warning (log + aviso no backend na
edição da página). Nunca rebentar a página pública por causa de um toggle.

## Encaixe com o resto

- `cms:build` (ver `04`) inclui os blocos dos plugins **ativos** no
  manifesto — committed, logo o estado de plugins que afeta a paleta passa
  pelo git, coerente com G5.
- Rotas de plugins registam-se antes do catch-all do CMS (ordem garantida
  pelo ficheiro de cache).
- Um plugin pode trazer domain models + Resources (ex.: Blog traz `Post`)
  — é o mesmo padrão dos models de dev, só que empacotado.

## Alternativa assumida

Este desenho é um `nwidart/laravel-modules` enxuto e ciente do CMS. Se o
boilerplate de sync/cache pesar, adotar o package e manter por cima apenas
o registo de blocos e o estado enabled.

## Pronto quando

- Criar `app/Plugins/X/` com Plugin.php + um bloco e, após `sync` +
  `cms:build`, o bloco aparece na paleta e renderiza numa página.
- `sync` resolve ordem de dependências e falha em ciclos.
- Boot com DB desligada arranca e regista os plugins do cache.
- Com `runtime_toggle=false`, o backend não tem nenhum caminho que escreva
  o ficheiro de cache (G1 verificado por teste).
- Página com bloco de plugin desativado renderiza sem o bloco e avisa.
