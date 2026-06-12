# 07 — Backend, Edição, Permissões (v2)

> Tudo no Filament. O builder de páginas, os forms de blocos e os CRUDs de
> tipos de admin são **gerados dos blueprints em runtime de leitura** —
> construir um form dinamicamente não é codegen, é só PHP a montar objetos
> (G1 intacto). Edição in-context não existe na v2.

## O editor de páginas

Uma Filament Page custom (não um Resource CRUD básico):
1. **Árvore** à esquerda: navegar, criar, mover, reordenar (drag), com as
   operações do `03`.
2. **Builder** ao centro: a lista ordenada de instâncias de blocos da
   draft revision. Adicionar bloco abre a paleta (lida de
   `resources/data/blocks.json` — ficheiro, não DB). Cada instância expande
   para o seu form.
3. **Form por bloco**: gerado do blueprint do bloco — mapa type → input:

| Type | Input Filament |
|---|---|
| `text` / `textarea` | `TextInput` / `Textarea` |
| `richtext` | `RichEditor` |
| `number` / `boolean` / `date` | `TextInput::numeric()` / `Toggle` / `DatePicker` |
| `select` | `Select` (opções do blueprint) |
| `media` | `SpatieMediaLibraryFileUpload` (collection = uid do bloco + nome do campo) |
| `link` | componente próprio: tabs interna (página/entry) vs externa (URL) |
| `relation` | `Select` com search sobre entries/models |
| `repeater` | `Repeater` com os subcampos, reordenável |

O **mesmo mapa** serve os forms de entries (`05`) — escrever uma vez
(`BlueprintFormBuilder`), usar em blocos e tipos.

## Fluxo de gravação

1. Submit → validação derivada do blueprint (required, types, opções).
2. **Sanitização de `richtext` na gravação** (allowlist de tags — HTML
   Purifier ou equivalente). Em modo view renderiza-se confiado porque a
   entrada foi limpa; defesa na escrita, não na leitura.
3. Persistir na **draft revision** (`cms_page_revisions`, `is_draft`).
4. Media: uploads anexam-se à Page via MediaLibrary na collection
   `{block_uid}.{field}`; o jsonb guarda `media_id`. Ao apagar uma
   instância de bloco, as suas collections limpam-se (observer).
5. Publicar = botão separado com permissão própria (ver abaixo) → fluxo
   do `03` (snapshot, ponteiro, invalidação de caches).

## Permissões (laravel-permission + policies)

Roles base: `admin`, `editor`, `publisher` (ajustar por projeto).
Permissões granulares como strings Spatie; o **scoping** é nas policies:

- `PagePolicy`: editar/publicar por ramo da árvore — a policy sobe a
  cadeia de parents e verifica permissões tipo `pages.edit:/produtos`.
  Publicar é permissão separada de editar (workflow draft → review →
  publish sai de graça das revisions).
- `EntryPolicy`: por tipo (`entries.edit:team-member`).
- `TypePolicy`: criar/alterar **tipos** é de `admin` — é a permissão mais
  poderosa do sistema (muda schema-de-dados); não dar a editores.
- Toggle de plugins (se `runtime_toggle`): só `admin`, auditado.

## Activity log

`LogsActivity` em `Page`, `Entry`, `CmsType` e nas operações estruturais:
create/update/delete, move/reorder, publish/unpublish/rollback (com id da
revision), alterações de blueprint de tipo, toggles de plugin. O histórico
de revisions + activity log responde a "quem mudou o quê, quando".

## Tipos de admin no menu

Itens de navegação por tipo (`cms_types`) com icon — gerados quando o
painel monta (runtime de leitura do admin, não boot — G3 intacto).
`EntryResource` genérica com form/table do blueprint (ver `05`).

## Pronto quando

- Editor compõe uma página (blocos, repeater, media, link interno),
  grava draft, faz preview assinado, publica — e cada passo respeita as
  permissões do seu role.
- Richtext malicioso é neutralizado na gravação (teste com payload XSS).
- Apagar uma instância de bloco limpa a media collection associada.
- Um `editor` sem `publish` consegue gravar drafts mas não publicar;
  um `publisher` de `/produtos` não publica fora desse ramo.
- O activity log mostra a cadeia completa de uma alteração até ao publish.
