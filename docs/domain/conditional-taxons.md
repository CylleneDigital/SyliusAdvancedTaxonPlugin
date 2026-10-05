# Conditional taxons

A conditional taxon gets its products from rules instead of manual assignment. The plugin
**materializes** the rules: matching products are attached to the taxon as regular Sylius
`ProductTaxon` links, so the storefront, the API, the search and the product forms see a normal
taxon. Nothing is resolved at query time. Admin usage: [User guide](../admin/user-guide.md).

## Model

| Element | Content |
|---------|---------|
| `sylius_taxon.conditional` (`isConditional()`) | The taxon is materialized from its conditions |
| `FacetCondition` (`cyllene_advanced_taxon_facet_condition`) | One rule: `conditionType`, `operator`, `referenceCode`, `value`, `position` |
| `AdvancedTaxonInterface::getFacetConditions()` | The rules of the taxon, ordered by `position` |

`conditional` is kept by the entity: `addFacetCondition()` sets it to `true`, and
`removeFacetCondition()` sets it to `false` when the last condition goes. Every entry point writing
conditions through these methods (admin, API, fixtures, import) therefore triggers the
synchronization. In the admin form, `conditional` is not a field: on submit, `TaxonTypeExtension`
also sets it to "has at least one condition". The synchronization only runs for a taxon whose flag
is `true` (or just turned `false`).

## Types and operators

`FacetCondition::OPERATORS_BY_TYPE`:

| Type (`conditionType`) | Constant | Operators | `referenceCode` | `value` |
|------------------------|----------|-----------|-----------------|---------|
| `attribute` | `TYPE_ATTRIBUTE` | `equals`, `not_equals`, `contains`, `not_contains` | attribute code (required, existing, text or select attribute) | required |
| `option` | `TYPE_OPTION` | `in`, `not_in` | option code (required, existing) | required |
| `name` | `TYPE_NAME` | `contains`, `not_contains`, `equals`, `not_equals` | - | required |
| `description` | `TYPE_DESCRIPTION` | `contains`, `not_contains` | - | required |
| `stock` | `TYPE_STOCK` | `is_in_stock` | - | not used |
| `taxon_membership` | `TYPE_TAXON` | `in`, `not_in` | taxon code (required, existing) | not used |

`TYPES_WITH_REFERENCE` = `attribute`, `option`, `taxon_membership`. `TYPES_WITHOUT_VALUE` = `stock`,
`taxon_membership`. `NEGATIVE_OPERATORS` = `not_equals`, `not_contains`, `not_in`.

The condition editor only offers attributes stored as `text` or `json` (text and select attributes),
the only values a condition reads.

### Semantics per type

| Type | A product matches when |
|------|------------------------|
| `attribute` | one of its values of the attribute `referenceCode` matches. **Text values** (`text` field, any locale): `equals` → `=`, `contains` → `LIKE '%value%'`. **Select values** (choice keys stored as JSON): a choice matches when its key, or one of its labels in any locale, equals / contains the value, **case-insensitively** (compared in PHP). Both kinds are combined with `OR` |
| `option` | one of its variants has an option value of option `referenceCode` whose **translated value, in any locale**, equals `value`. `in` takes a single value |
| `name` | its name in the synchronization locale equals / contains `value` |
| `description` | its description in the synchronization locale contains `value` |
| `stock` | one of its **enabled** variants is not tracked, or has `onHand - onHold > 0` |
| `taxon_membership` | it is directly assigned (`ProductTaxon`) to the taxon with code `referenceCode`; descendants of that taxon do not count |

`contains` patterns are escaped: `%`, `_` and the escape character typed by the merchant are literal.
Every comparison ignores the case, on every database: the SQL ones (name, description, attribute
text, option value) compare `LOWER()` of both sides, the select attribute choices are compared in
PHP.

### Negative operators

`not_equals`, `not_contains` and `not_in` mean **the product owns no matching value**, not "the
product owns a value that differs". They are compiled to a `NOT EXISTS` subquery with the positive
operator (`FacetCondition::negatedOperator()`):

- `not_in` on an option excludes every product with a variant carrying the option value;
- `not_in` on a taxon excludes every product assigned to it;
- `not_equals` / `not_contains` on an attribute exclude every product owning a matching value, and
  keep the products without the attribute.

`name` and `description` work on a single translation row: their negative operators are a plain
`!=` / `NOT LIKE` that also accepts an empty (`NULL`) field or a missing translation.

For a given value, a positive operator and its negation partition the catalog.

### Combination

All conditions of a taxon are combined with `AND`. Each condition joins its own aliases: two option
conditions may be satisfied by two different variants. A product is listed once (`DISTINCT`).

## Validation

`ValidFacetCondition` (class constraint on `FacetCondition`, group `sylius`; the taxon form validates
the collection with `Valid`):

| Check | Message key (`validators`) | Path |
|-------|----------------------------|------|
| Type not in `OPERATORS_BY_TYPE` | `cyllene_digital_sylius_advanced_taxon.facet_condition.invalid_type` (other checks skipped) | `conditionType` |
| Operator not allowed for the type | `….facet_condition.invalid_operator` | `operator` |
| Reference empty (after trim) for a type with reference | `….facet_condition.reference_required` | `referenceCode` |
| A `taxon_membership` condition referencing its own taxon | `….facet_condition.self_reference` | `referenceCode` |
| No attribute (stored as text or select), option or taxon with that code (`FacetReferenceCheckerInterface::exists()`) | `….facet_condition.reference_not_found` (`%code%`) | `referenceCode` |
| Value empty (after trim) for a type with value | `….facet_condition.value_required` | `value` |
| Reference or value longer than 255 characters (`MAX_LENGTH`) | `….facet_condition.too_long` (`%limit%`) | `referenceCode` / `value` |

## Fail closed

`ConditionalTaxonQueryBuilder::buildQuery()` keeps a condition only when it is **applicable**: operator
allowed for its type, reference present and existing when required (`FacetReferenceChecker`, cached
per request; an attribute must be stored as text or select, the only values conditions read), not
the taxon itself for `taxon_membership`, value present when required. `setReferenceCode()` stores the
code trimmed, so that a stray space never makes the reference miss. If any stored condition is not applicable, or if there is
none, the query gets `1 = 0` and the taxon matches **no** product. Incomplete data written without
validation (API, fixtures, import, SQL), or a reference deleted or mistyped, never widens the
selection to the whole catalog: without this check, a negative operator on a missing reference
(`NOT EXISTS` always true) would match every product.

## Synchronization

`ConditionalTaxonProductAssigner::syncTaxon(AdvancedTaxonInterface $taxon, ?string $localeCode = null)`
returns `{detached, attached, matched}` and does not flush.

| Case | Result |
|------|--------|
| Taxon not conditional | every `ProductTaxon` of the taxon is detached |
| Conditional, no condition | nothing matches: every product is detached |
| Conditional | matching ids from `buildQuery()`; current links not matching are detached, missing ones attached |

Rules:

- **Differential.** Products that stay assigned keep their `ProductTaxon` and its position. New links
  are appended after the highest remaining position (`0` for an empty taxon).
- **Disabled products are kept.** `buildQuery()` does not filter on `enabled`: a product disabled for a
  while keeps its assignment and position. The storefront lists enabled products only.
- **The synchronization owns the links.** A product assigned by hand to a conditional taxon is
  detached at the next synchronization if it does not match.
- **Leaving conditional mode detaches everything**, including products that were assigned by hand
  before the taxon became conditional.
- Links are removed through the unit of work (`removeProductTaxon()` + `remove()`), so Doctrine
  events fire (search indexers…); they are loaded by chunks of 1000.
- **Locale.** `name` and `description` are read in `$localeCode`, by default the Sylius default
  locale (`sylius.translation_locale_provider`, `getDefaultLocaleCode()`).
- **Channel.** The match is not restricted to a channel.

### Triggers

`ConditionalTaxonSyncListener` (Doctrine `onFlush` + `postFlush`), whatever the entry point:

| Change in the flush | Collected |
|---------------------|-----------|
| Insertion of a taxon with `conditional = true` | yes |
| Update of a taxon whose `conditional` field changed | yes (marked "turned off" when it became `false`) |
| Insertion, update or deletion of a `FacetCondition` with a taxon | its taxon |
| Update or deletion of a taxon `facetConditions` collection | its owner |
| Any other taxon change (name, slug, tree position, media…) | no |
| Product, attribute, option or stock changes | no (use the [command](#console-command)) |

In `postFlush`, the listener keeps the id of each collected taxon still managed that is conditional,
or has just been turned off. A regular taxon whose conditions changed without being conditional is
skipped. A deleted taxon is skipped (its links go with it, `ON DELETE CASCADE`).

The `SynchronizeConditionalTaxon(taxonId)` messages go out once the flush is over: at the end of the
request (`kernel.response`, before the response is sent), of the console command
(`console.terminate`) or of the message a worker handled (`WorkerMessageHandledEvent`). Doctrine
does not support a flush inside `postFlush`, and a synchronous handler flushes. A flag prevents
re-entrance while dispatching. Code that flushes outside these three (a plain script) leaves the
synchronization to the [command](#console-command).

### Messenger

| Element | Value |
|---------|-------|
| Message | `CylleneDigital\SyliusAdvancedTaxonPlugin\Message\SynchronizeConditionalTaxon` (`int $taxonId`) |
| Dispatched on | `sylius.command_bus`, whose `doctrine_transaction` middleware wraps the handler in a transaction; the handler is registered on this bus only |
| Handler | `SynchronizeConditionalTaxonHandler` (tag `messenger.message_handler`, bus `sylius.command_bus`) |
| Default | synchronous: handled at the end of the request that saved the taxon |

The handler reloads the taxon (a deleted one is ignored) and calls `syncTaxon()`; the
`doctrine_transaction` middleware of the bus flushes the assignments.

On a large catalog, route the message to an asynchronous transport, so that saving a taxon does
not wait for the assignments: [Configuration](../integration/configuration.md#conditional-taxon-synchronization-messenger).
The products then appear once a worker has handled the message.

### Console command

```bash
bin/console cyllene:advanced-taxon:sync-conditional-taxons
```

Calls `syncAllConditionalTaxons()`: loads the taxons with `conditional = true` by batches of 50,
ordered by id, synchronizes each one in the default locale, then flushes and clears the entity
manager after each batch. Output:

```
[OK] Conditional taxons synced: <taxons> taxons, <matched> matched products, <attached> attached, <detached> detached.
```

`matched` is the sum of the matches of every taxon. Running it twice changes nothing the second time.
Schedule it ([Installation, step 6](../integration/installation.md#6-keep-conditional-taxons-in-sync))
to follow catalog changes the listener does not see: new products, attribute or option values,
stock.

## Admin preview

The conditions tab posts the rows being edited to `AdminFacetPreviewController`
([route contract](../architecture/public-contract.md#admin-route)), which calls
`ConditionalTaxonQueryBuilder::previewConditions()`:

- rows that are not applicable (incomplete, still being filled, or whose reference does not exist)
  are **skipped**; the others are previewed. With no applicable row, the answer is `count: 0`;
- the products are the ones the synchronization assigns: disabled products included, `name` and
  `description` read in the Sylius default locale (`sylius.translation_locale_provider`), the
  translation being optional;
- the answer holds the total count and up to 20 products (the query has no `ORDER BY`); at most 32
  conditions are read.

## Known limitations

| Limitation | Effect |
|------------|--------|
| Option conditions compare the **translated** option value, not the option value code | Renaming an option value in every locale breaks the condition: positive conditions stop matching, `not_in` stops excluding |
| Deleting a referenced attribute, option or taxon | The condition is no longer applicable: the taxon matches no product, and the next synchronization (command or condition change) detaches all its products |
| `name` / `description` use the Sylius default locale | A product without a translation in that locale never matches `equals` / `contains` on these types |
| `taxon_membership` on another conditional taxon | Reads that taxon's current links: the command processes taxons by id, so a dependency synchronized later is only seen at the next run |
| `syncAllConditionalTaxons()` clears the entity manager | Meant for the console; calling it in a request detaches every loaded entity |
| No trigger on catalog changes | Products created or changed after the last save are only picked up by the command |
| A condition on a select attribute reads every value of that attribute | The choices are matched in PHP (no `LIKE` on a JSON column), then the matching products are passed to the query by id: on a very large catalog, a frequent value makes a long `IN` list |
