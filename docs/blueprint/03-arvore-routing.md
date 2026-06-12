# 03 — Árvore de Páginas, Routing, Locales, Draft/Publish (v2)

> A árvore é relacional (`parent_id` + `position`), leve, cacheada inteira.
> O routing é locale-aware desde o dia 1. O público vê sempre a revision
> publicada; o editor vê o rascunho via preview assinado.

## Modelo

`App\Models\Cms\Page`. Relações: `parent()`, `children()` (ordenado por
`position`), `translations()` (via `translation_group_id`),
`revisions()`, `publishedRevision()`, `draftRevision()`.

## Árvore em memória + cache

Uma query (`orderBy parent_id, position`), agrupar por `parent_id`, montar.
`cache()->rememberForever('cms.tree.{locale}', ...)`; invalidar em qualquer
save/move/delete/publish. Nested-set/CTE só se um dia a árvore for enorme —
não antecipar (`staudenmeir/laravel-adjacency-list` é o plano B).

Derivada da árvore, uma **lookup de paths** cacheada por locale:
`['/produtos/widget' => 42, ...]` — path completo → page_id. Reconstruída
junto com o cache da árvore. A resolução por request é um array access, não
uma travessia.

## Routing com locales

```php
// DEPOIS de todas as rotas específicas (admin, plugins, API):
Route::get('/{locale}/{path?}', [PageController::class, 'show'])
    ->where('locale', '[a-z]{2}')
    ->where('path', '.*')
    ->name('cms.page');
Route::get('/', RedirectToDefaultLocale::class);   // negocia/redireciona
```
- Locales ativos e default em `config/cms.php` — config, não DB (G3).
- **Homepage por locale:** path vazio resolve para a página raiz marcada
  como home (convenção: o primeiro filho raiz com slug reservado `home`,
  servido em `/{locale}` sem o slug aparecer no URL).
- Paths reservados (`admin`, `api`, `storage`, ...) são recusados como slug
  na validação de página — não confiar só na ordem das rotas.
- Sem locale no URL de uma página existente noutro locale → 404 limpo
  (nada de fallback silencioso); hreflang gerado a partir do
  `translation_group_id`.

## Resolução de um request

1. Lookup `path → page_id` no cache do locale; falhou → 404.
2. Carregar a página. `status != published` → 404, **exceto** preview.
3. Carregar `publishedRevision->data` (ou a draft, em preview).
4. Entregar à camada de render (ver `04-blocks.md`).

## Preview de rascunhos

URL assinado (`URL::temporarySignedRoute`) gerado no backend: o editor
clica "Preview" e abre a página pública com a **draft revision** e um banner
de preview. Sem sessão partilhada esquisita, sem cookie mágico: assinatura +
expiração + (opcional) exigir login com permissão de preview.

## Publicar / despublicar

- **Publicar:** snapshot da draft → nova revision imutável →
  `published_revision_id` aponta para ela → `status = published` →
  invalidar caches (árvore, lookup, página).
- **Despublicar:** `status = draft`; a revision publicada fica no histórico.
- **Rollback:** apontar `published_revision_id` para uma revision anterior.
  É um UPDATE, não uma migração de dados.

## Mover / reordenar

- Reordenar irmãos = `position`. Mover ramo = `parent_id`.
- Validar slug único entre os novos irmãos (mesmo locale).
- Qualquer mutação estrutural invalida árvore + lookup de paths do locale
  (e gera entrada no activity log — ver `07`).

## Pronto quando

- Páginas aninhadas multilingues resolvem em `/{locale}/{path}`; homepage
  resolve em `/{locale}`; paths inexistentes/não publicados dão 404.
- Preview assinado mostra a draft; o público nunca a vê.
- Publicar cria revision imutável; rollback funciona com um clique.
- Mover/reordenar atualiza paths dos descendentes e invalida caches.
- Slugs reservados são recusados na validação.
