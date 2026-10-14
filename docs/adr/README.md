# Architecture decision records

One file per decision: its context, what was decided, what follows from it. A decision is not
edited once accepted: a new record supersedes it, and the old one says so in its status.

A record holds the decision and its reasons, which do not move with the code. What does - file
paths, class lists, figures - is in the [architecture overview](../architecture/overview.md), the
[code map](../development/code-map.md), the [public contract](../architecture/public-contract.md)
and the guides, which follow the code: a record that quoted them would be wrong at the next
refactoring, and could not be corrected. A record explains a choice, it promises nothing: only the
public contract says what is stable.

A new record takes the next number, the template of the others (Status, Date, Context, Decision,
Consequences, Related) and a line in the table below.

| # | Decision |
|---|---|
| 0001 | [The plugin never replaces a Sylius model](0001-never-replace-a-sylius-model.md) |
| 0002 | [A universe is kept out of main taxons by a validation constraint, not a form override](0002-universe-kept-out-of-main-taxons-by-a-constraint.md) |
| 0003 | [Plugin media zones replace the native taxon Images section](0003-media-zones-replace-the-native-images-section.md) |
| 0004 | [Twig hooks are imported, not prepended](0004-twig-hooks-imported-not-prepended.md) |
| 0005 | [The plugin takes over the Sylius search form so it keeps the advanced filters](0005-search-form-taken-over-to-keep-the-advanced-filters.md) |
| 0006 | [The taxon listing is the Sylius grid](0006-taxon-listing-is-the-sylius-grid.md) |
| 0007 | [Facet counts start from the query the grid lists](0007-facet-counts-start-from-the-grid-query.md) |
| 0008 | [Advanced filters are counted subqueries, with caps on codes and values](0008-advanced-filters-are-counted-subqueries-with-caps.md) |
| 0009 | [No `LIKE` on JSON columns](0009-no-like-on-json-columns.md) |
| 0010 | [Featured items are filtered for visibility at render time](0010-featured-items-filtered-at-render-time.md) |
| 0011 | [Conditional taxons are materialized as product-taxon links](0011-conditional-taxons-materialized-as-product-taxon-links.md) |
| 0012 | [A conditional taxon owns its product links](0012-conditional-taxon-owns-its-product-links.md) |
| 0013 | [Products are detached through the unit of work, not a DQL `DELETE`](0013-products-detached-through-the-unit-of-work.md) |
| 0014 | [The synchronization is dispatched through Messenger once the flush is over, synchronous unless routed](0014-synchronization-dispatched-through-messenger-after-the-flush.md) |
| 0015 | [A condition that cannot apply matches nothing](0015-non-applicable-condition-matches-nothing.md) |
| 0016 | [Conditions compare case-insensitively on every database](0016-conditions-compare-case-insensitively.md) |
| 0017 | [Option conditions match the translated value, and `in` takes one value](0017-option-conditions-match-the-translated-value.md) |
| 0018 | [The condition preview mirrors the synchronization](0018-condition-preview-mirrors-the-synchronization.md) |
| 0019 | [Pictograms are Sylius images](0019-pictograms-are-sylius-images.md) |
| 0020 | [Values rendered in `style` or `href` are sanitized on write](0020-values-sanitized-on-write.md) |
| 0021 | [Template icons are local](0021-template-icons-are-local.md) |
| 0022 | [An icon the shop cannot render is refused on save](0022-icon-the-shop-cannot-render-is-refused.md) |
| 0023 | [The random media display modes are kept](0023-random-media-display-modes-kept.md) |
| 0024 | [No script in the templates, behaviour in Stimulus controllers](0024-no-script-in-templates.md) |
| 0025 | [Twig functions are served by lazy runtimes](0025-twig-functions-served-by-lazy-runtimes.md) |
| 0026 | [Help texts only where a field's effect is not obvious](0026-help-texts-only-where-the-effect-is-not-obvious.md) |
| 0027 | [Translation keys keep their namespaces](0027-translation-keys-keep-their-namespaces.md) |
| 0028 | [Services are declared explicitly, without autowiring](0028-explicit-services-no-autowiring.md) |
| 0029 | [Services holding per-request memory are reset](0029-per-request-memory-services-are-reset.md) |
| 0030 | [A `Bundle` class with `SyliusPluginTrait`, not `AbstractBundle`](0030-bundle-class-with-sylius-plugin-trait.md) |
| 0031 | [A schema change is a new migration, written against the DBAL `Schema` API](0031-schema-change-is-a-new-migration.md) |
| 0032 | [No `CHANGELOG.md`](0032-no-changelog.md) |
| 0033 | [A workaround for an upstream bug never constrains the shops](0033-upstream-workarounds-never-constrain-shops.md) |
| 0034 | [Static analysis and coverage run on the primary CI job only](0034-static-analysis-on-the-primary-ci-job.md) |
| 0035 | [The contributor stack ships a Chrome service](0035-contributor-stack-ships-chrome.md) |
