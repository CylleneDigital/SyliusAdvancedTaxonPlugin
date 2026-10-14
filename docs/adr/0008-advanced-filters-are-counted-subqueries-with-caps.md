# 0008. Advanced filters are counted subqueries, with caps on codes and values

- Status: accepted
- Date: 2026-10-14

## Context

The advanced filters come from the query string, which anyone can write. Each filtered attribute
or option added joins: a forged URL could join more tables than MySQL accepts and answer an error,
or send heavy queries for every page view without being logged in. With about ten filters as
joins, the join order search of MySQL does not finish; an `EXISTS` subquery does not help, MySQL
turns it back into joins.

## Decision

Each attribute or option filter is a correlated count subquery compared to zero. The number of
filtered codes and the number of values per code are capped; what goes beyond is ignored.

## Consequences

- The query stays the same shape whatever the database, and a forged URL cannot make it grow
  without limit.
- A shop that needs more filters at once than the caps allow cannot get them from the URL.
- The caps are constants of the plugin, not configuration.

## Related

- [0006. The taxon listing is the Sylius grid](0006-taxon-listing-is-the-sylius-grid.md)
- [0009. No `LIKE` on JSON columns](0009-no-like-on-json-columns.md)
