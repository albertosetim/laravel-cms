# 05 — Tipos Criados pelo Admin + Caminho de Promoção (v2)

> "Deixar o admin criar models" — sim, mas honrando G1/G7: o que o admin
> cria é um **blueprint (dados)**; o sistema serve um CRUD dinâmico tão bom
> que parece um model. Quando um tipo cresce, **promove-se** a código real
> por um portão dev-time. Nada de codegen em runtime, nunca.

## Criar um tipo (admin, backend)

O admin define no Filament: nome, slug, icon, campos (mesmos types dos
blocos: `text`, `richtext`, `media`, `select`, `date`, `repeater`,
`relation`, ...), e opções (tem slug público? por locale? ordenável?).
Resultado: uma linha em `cms_types` com o `blueprint` jsonb. **Nenhum
ficheiro é tocado.**

## Entries: um model, todos os tipos

`App\Models\Cms\Entry` sobre `cms_entries`. O `type_id` diz qual blueprint
aplicar. Scopes essenciais: `ofType($slug)`, `published()`, `inLocale($l)`,
`whereField($name, $value)` (traduz para query jsonb que usa o GIN).

```php
Entry::ofType('team-member')->published()->whereField('department', 'sales')->get();
```

## Backend dinâmico (a ilusão de "model")

**Uma** Filament Resource genérica (`EntryResource`) serve todos os tipos:
- O menu do backend mostra um item por tipo (lê `cms_types` — runtime do
  admin, não boot, logo G3 intacto).
- Form gerado do blueprint: cada campo do blueprint → input Filament
  (mesmo mapa dos blocos, ver `07`).
- Table com colunas marcadas como "listáveis" no blueprint; filtros sobre
  campos `select`/`boolean` via query jsonb.
- Validação derivada do blueprint (required, types) na gravação.

Para o admin, isto é indistinguível de um model feito por dev. Para o
sistema, são dados (G7).

## Blocos que consomem tipos

O type de campo `relation` e blocos de listagem fecham o ciclo: o admin
cria o tipo "Team Member", cria entries, e arrasta para uma página um bloco
`entry-list` configurado com `type: team-member` — o bloco faz a query por
ele. Dev não escreveu nada específico.

## Evolução do blueprint com entries existentes

- **Adicionar campo:** entries antigos não o têm → o render aplica o
  default do blueprint. Trivial.
- **Remover campo:** valores ficam órfãos no jsonb (inofensivo); o comando
  `cms:types:audit` lista órfãos e pode limpá-los explicitamente.
- **Renomear campo:** a UI trata rename como rename (atualiza o blueprint
  **e** migra as chaves nos entries do tipo, numa transação) — nunca como
  remove+add, que perderia conteúdo silenciosamente.
- **Mudar type de campo:** só pares compatíveis (text→textarea sim,
  richtext→number não); incompatível exige limpar ou promover.

## O portão de promoção: `cms:promote:type {slug}`

Sinais de que um tipo de admin deve virar código: precisa de FKs duras,
queries que o GIN não serve, lógica de domínio, integrações. Aí o dev corre
(em **dev**, nunca em produção):

```
php artisan cms:promote:type team-member
```
O comando lê o blueprint de `cms_types` e emite **ficheiros reais**:
- `app/Models/TeamMember.php` — colunas tipadas, casts, relações;
- `database/migrations/...create_team_members_table.php` — colunas a partir
  dos campos, índices nos marcados como filtráveis (G4);
- `app/Filament/Resources/TeamMemberResource.php`;
- `database/migrations/...migrate_team_member_entries.php` — **migração de
  dados**: copia os entries do jsonb para a tabela nova e marca o tipo como
  promovido (o tipo desaparece da UI de tipos do admin; os entries antigos
  ficam soft-deleted até limpeza).

Regras (iguais ao generator da v1): gera **uma vez**, recusa-se a esmagar
ficheiros existentes, o dev revê e commita, `migrate` corre no pipeline.
Evolução posterior = migrations novas, nunca regenerar. Implementação:
stubs próprios ou Laravel Blueprint (jasonmccreary) como motor.

## Quando NÃO usar tipos de admin

Encomendas, produtos com stock, qualquer coisa com dinheiro, FKs duras ou
integridade transacional: **nasce como código** (`cms:promote:type` também
aceita um spec YAML em vez de um tipo existente, fazendo as vezes do
`cms:make:type` da v1). O tipo de admin é para conteúdo editorial
estruturado: equipa, FAQs, testemunhos, parceiros, vagas.

## Pronto quando

- Admin cria um tipo, define campos, cria entries, lista/filtra no backend
  — sem nenhum ficheiro escrito e sem deploy.
- Um bloco `entry-list` numa página renderiza entries publicados do tipo,
  filtrados por um campo jsonb (query usa o GIN — verificar com explain).
- Rename de campo migra as chaves dos entries sem perda.
- `cms:promote:type` emite model+migration+resource+migração de dados
  revisáveis; depois da promoção o tipo é só-código.
- Nenhum destes fluxos escreve ficheiros a partir de um request web (G1).
