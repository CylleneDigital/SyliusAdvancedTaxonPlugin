# 0007. Facet counts start from the query the grid lists

- Status: accepted
- Date: 2026-10-14

## Context

The sidebar shows, for each attribute, option and sub-taxon, how many products match. Counts
computed on another product set than the listing would announce products the page does not show.

## Decision

The facet queries start from a base query that selects exactly what the grid lists: enabled products
of the current channel, in the taxon or its descendants, with the grid's translation join, its
search and its descendants setting. Each facet family ignores its own selection when it counts the
alternatives.

## Consequences

- A change to what the grid lists has to be made in the base query too.
- Choosing a value never shows a count the listing contradicts.

## Related

- [0006. The taxon listing is the Sylius grid](0006-taxon-listing-is-the-sylius-grid.md)
- [0008. Attribute and option filters are counted subqueries, with caps](0008-advanced-filters-are-counted-subqueries-with-caps.md)
