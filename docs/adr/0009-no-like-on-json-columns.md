# 0009. No `LIKE` on JSON columns

- Status: accepted
- Date: 2026-10-14

## Context

The values of a select attribute are stored in a JSON column. Matching them with `LIKE` works on
MySQL and MariaDB, not on PostgreSQL, and depends on how the JSON is serialized.

## Decision

The select values of an attribute, and its choice labels, are read once per attribute code and
request, and matched in PHP; the query then filters on the identifiers of the matching values.

## Consequences

- Every value of a filtered select attribute is read, once per request and code: an accepted cost on
  a large catalog.
- The filters behave the same on MySQL, MariaDB and PostgreSQL.

## Related

- [0007. Facet counts start from the query the grid lists](0007-facet-counts-start-from-the-grid-query.md)
- [0008. Attribute and option filters are counted subqueries, with caps](0008-advanced-filters-are-counted-subqueries-with-caps.md)
- [0016. Conditions compare case-insensitively on every database](0016-conditions-compare-case-insensitively.md)
