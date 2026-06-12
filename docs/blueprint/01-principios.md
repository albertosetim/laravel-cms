# 01 — Princípios e Regras de Ouro (v2)

> Lê isto primeiro. As regras G1–G6 vêm da v1 e mantêm-se inegociáveis; a v2
> acrescenta a G7, que é a que permite o modo "GodLike" sem partir as outras.
> Se uma feature parecer exigir violar uma regra, o desenho está errado, não
> a regra.

## As três camadas e os seus donos

1. **Schema / blueprint** — *a forma*: que campos, que types, que blocos.
   Autoria: dev (blocos, templates) **ou admin (tipos editoriais)** — mas
   quando é o admin, o blueprint é **dados na DB**, nunca ficheiro.
2. **Conteúdo** — *os valores*. Autoria: editor, em runtime. Vive na DB.
3. **Apresentação** — Blade. Consome schema + conteúdo e renderiza.
   Autoria: dev, sempre ficheiro, sempre git.

## Regras de ouro

### G1 — Nunca escrever ou executar código em runtime de produção
Não gerar PHP, não correr `migrate`, não escrever em `app/`/`resources/` a
partir de um request web. FS read-only/efémero, multi-servidor, DDL sem
rollback no MySQL, codegen a partir de input = RCE. Toda a geração de código
é dev-time e o output entra no git.

**Corolário v2 (corrige a falha da v1):** isto aplica-se também a
`bootstrap/cache/*`. Qualquer materialização de cache a partir do admin é
exceção explícita, atrás de config, documentada para single-server
(ver `06-plugins.md`).

### G2 — Nunca EAV / key-value meta aberto
Nada de tabela `meta(key, value)`. Atributos consultáveis → coluna tipada.
Documento coeso → JSON com forma conhecida pelo blueprint. A v2 dá poder ao
admin **sem** EAV: um entry é um documento jsonb cuja forma o blueprint dita
— consultável via GIN, validável, com schema visível no backend.

### G3 — Boot nunca depende da base de dados
Registo de plugins e descoberta de blocos lêem **ficheiros de cache**
gerados por artisan. A app arranca com a DB em baixo. O admin escreve estado
na DB; comandos materializam o cache; o boot só lê.

### G4 — A linha JSON vs coluna tipada vs proibido
- **Coluna tipada + model real:** atributos filtráveis/relacionáveis com
  integridade dura. Default para domain models de dev.
- **JSON (jsonb) com forma de blueprint:** documentos coesos — body de
  página (árvore de blocos), entries de tipos de admin. Consultável via GIN
  quando preciso.
- **Key/value aberto:** nunca.

### G5 — Git é a autoridade do que corre em produção
Migrations, models, blocos, templates, blueprints extraídos, plugins: tudo
no repo. O pipeline de CI **valida** (não regenera): se `cms:build` em CI
produz output ≠ committed, o build falha.

### G6 — Não lutar contra o framework
Antes de inventar, verificar o que o Laravel já dá: components anónimos/de
classe, `Blade::componentNamespace`, service providers, view composers,
casts, policies, cache tags. O builder de páginas da v2 é ~90% Blade
components + 10% cola.

### G7 — O admin cria dados; o dev cria código *(nova)*
Tudo o que o admin "cria" — tipos, páginas, blocos numa página — é uma linha
ou um documento na DB. O sistema serve-lhe UI dinâmica (Filament a partir do
blueprint) para que isso **pareça** criar models e componentes. Quando um
tipo de admin precisa de FKs duras, queries pesadas ou lógica, **promove-se**
a código por um comando dev-time (`cms:promote:type`) — a fronteira
atravessa-se por um portão explícito, nunca por fusão das camadas.

Frase de bolso: *o admin cria tipos e entries (dados); o dev cria blocos e
models (código); a promoção é a ponte, e é dev-time.*

## Convenções (uma só base de dados)

- Prefixo de tabelas do CMS: `cms_`.
- Namespace de models do CMS: `App\Models\Cms\`.
- Domain models em `App\Models\`, tabelas sem prefixo.
- Migrations do CMS em `database/migrations/cms/`.
- Sem segunda connection: o CMS precisa de JOINs, FKs e transações com
  users, permissões e domain models.

## Estado global com juízo

Contextos de render (`CmsRenderContext`) são **scoped bindings**
(`$app->scoped(...)`), não singletons — sob Octane/queues um singleton vaza
entre requests. Regra geral: qualquer estado por-request usa scoped.

## Pronto quando

- Este documento está no repo e a equipa concorda com G1–G7.
- Uma só connection ativa; prefixos e namespaces definidos.
- O pipeline de CI tem o passo de validação de blueprints (G5).
