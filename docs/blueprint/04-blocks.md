# 04 — Blocos e Page Builder (v2)

> O coração do "GodLike": o admin **compõe componentes nas páginas**. Um
> bloco é um Blade component normal autorado por dev; a página é um
> documento JSON com a lista ordenada de instâncias de blocos. Zero parsing
> em runtime, zero DSL — é a API de components do Blade a fazer o trabalho.

## Conceitos

- **Bloco** (dev, ficheiro, git): Blade component com campos `x-cms`.
  Ex.: `hero`, `richtext`, `gallery`, `team-list`, `contact-form`.
- **Template/layout** (dev, ficheiro, git): o Blade que envolve o body —
  header, footer, zonas. Declara onde os blocos entram.
- **Página** (admin, DB): nó da árvore + revisions cujo `data` é a árvore
  de instâncias de blocos com os seus valores.

## Anatomia de um bloco

```
resources/views/components/cms/blocks/hero.blade.php        # core
app/Plugins/Blog/resources/views/blocks/post-list.blade.php # de plugin
```
```blade
{{-- hero.blade.php --}}
<x-cms.block name="hero" label="Hero" icon="photo">
  <section class="hero">
    <h1><x-cms.field name="title" type="text" required /></h1>
    <x-cms.field name="subtitle" type="text" />
    <x-cms.field name="image" type="media" />
    <x-cms.field name="cta" type="link" />
  </section>
</x-cms.block>
```
Regras de autoria (inegociáveis, validadas pelo `cms:build`):
- **Campos declaram-se incondicionalmente** — nunca dentro de `@if`,
  `@foreach`, `@auth`, etc. O modo collect renderiza o template uma vez com
  contexto neutro; um campo condicional registar-se-ia de forma instável.
  O build **falha com erro claro** se detetar registo instável (renderiza
  duas vezes com seeds de contexto diferentes e compara).
- Repetição é o type `repeater`, nunca `@foreach` escrito pelo autor.
- Lógica de apresentação (`@if` sobre **valores**) é livre em modo view —
  a restrição é só sobre a **declaração** dos campos.

## Os dois modos (a v2 elimina o modo `edit`)

`CmsRenderContext` (scoped binding, nunca singleton — Octane) define o modo:

1. **`collect`** — usado por `cms:build`. Cada `x-cms.field` regista
   `(block, name, type, opções)` no `SchemaCollector` e devolve string
   vazia. O `x-cms.block` regista o bloco (name, label, icon) e renderiza o
   slot para os filhos se registarem.
2. **`view`** — render público. `x-cms.field` lê o valor da instância
   corrente do bloco e renderiza-o conforme o type (escapado; `richtext`
   sanitizado na **gravação**, ver `07`; `media` via MediaLibrary →
   `<picture>` com conversions).

A edição acontece no backend (Filament) a partir do blueprint — não há
modo `edit` no frontend.

## `cms:build` — extração de blueprints

1. Descobre blocos: core (`resources/views/components/cms/blocks/`) +
   plugins ativos (cada plugin regista o seu namespace de views, ver `06`).
2. Renderiza cada bloco em modo `collect` num ambiente neutro (sem DB,
   view composers desligados, dados stub).
3. Valida estabilidade (dupla renderização) e nomes únicos.
4. Serializa **um manifesto**: `resources/data/blocks.json` — paleta
   completa (blocos, labels, icons, campos, types, defaults). Committed.
   CI valida que está fresco (G5); **nunca** se gera por request.

```json
{ "blocks": {
    "hero": { "label": "Hero", "icon": "photo", "fields": [
      { "name": "title", "type": "text", "required": true },
      { "name": "image", "type": "media" },
      { "name": "cta",   "type": "link" } ] },
    "blog.post-list": { "label": "Posts", "plugin": "blog", "fields": [ ... ] }
} }
```

## O documento de página (revision `data`)

```json
{ "blocks": [
  { "id": "b1u4...", "block": "hero",
    "values": { "title": "Willkommen", "image": {"media_id": 17}, "cta": {"page_id": 4, "label": "Mehr"} } },
  { "id": "c9k2...", "block": "richtext", "values": { "content": "<p>…</p>" } }
] }
```
- `id` é um UID estável por instância (gerado na criação no backend) —
  necessário para media collections e diffs entre revisions.
- `media` guarda `media_id` (MediaLibrary), nunca um path solto.
- `link` guarda `page_id`/`entry_id` ou URL externo — links internos
  sobrevivem a renames de slug.

## Render público: dynamic components, zero cola

```blade
{{-- dentro do template/layout da página --}}
@foreach ($revision->blockInstances() as $instance)
  <x-dynamic-component :component="'cms.blocks.' . $instance->block"
                       :instance="$instance" />
@endforeach
```
O `x-cms.field` em modo view lê de `$instance->values[$name]` (injetado via
contexto do bloco corrente). Sem parsing, sem eval — components Blade
pré-compilados. Cache opcional do HTML por página+revision, invalidado no
publish (cache derivado, nunca fonte de verdade).

## Types de campo (mínimo) e o `repeater` a sério

`text`, `textarea`, `richtext`, `media`, `boolean`, `number`, `date`,
`select`, `link`, `relation` (para entries/models), `repeater`.

**`repeater`** — o type mais difícil; especificação completa:
- Declaração: `<x-cms.repeater name="slides">` com `x-cms.field` filhos;
  em collect regista os subcampos sob o repeater.
- Storage: array de objetos dentro de `values`, cada item com `id` próprio.
- View: o componente itera os itens e renderiza o slot por item (o
  contexto de campo aponta para o item corrente).
- Backend: Filament `Repeater` gerado do blueprint, com reordenação.
- Nesting: **um nível** na v2. Repeater dentro de repeater fica para
  depois — é onde builders morrem; não abrir já.

## Templates/layouts e zonas

Um template declara zonas (`<x-cms.zone name="main" />`); o documento da
página pode agrupar blocos por zona (`"zone": "main"`). Default: uma zona
única `main` — só complicar quando um layout real precisar de duas.

## Pronto quando

- `cms:build` gera `blocks.json` correto, falha em campos condicionais e
  em nomes duplicados, e o CI valida frescura.
- Uma página composta no backend (hero + richtext + repeater) renderiza
  correta em modo view, com media via conversions e links internos por id.
- Nenhum parsing de Blade nem geração de blueprint acontece por request.
- Repeater funciona ponta a ponta (declarar → build → editar → render).
