# 0006. The taxon listing is the Sylius grid

- Status: accepted
- Date: 2026-10-14

## Context

The plugin adds products of child taxons and advanced filters to the taxon page. A product query
of its own, in a Twig function, would lose what the Sylius grid does: pagination, sorting, search,
channel restriction, and what other plugins add to it. Decorating the product repository is more
intrusive and clashes with the other plugins decorating it too.

## Decision

The plugin listens to the definition of the shop product grid and points its repository method
to a query builder that calls the native one, then adds the children products and the advanced
filters to the same query. An application that already replaced that method keeps its own query.

## Consequences

- No parallel product query in the templates: the grid stays the only listing.
- The grid query builder is public contract.
- An application with its own listing query does not get the plugin filters on it.

## Related

- [0007. Facet counts start from the query the grid lists](0007-facet-counts-start-from-the-grid-query.md)
- [0008. Advanced filters are counted subqueries, with caps on codes and values](0008-advanced-filters-are-counted-subqueries-with-caps.md)
