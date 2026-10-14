# 0009. No `LIKE` on JSON columns

- Status: accepted
- Date: 2026-10-14

## Context

The values of a select attribute are stored in a JSON column. Matching them with `LIKE` works on
MySQL and MariaDB, not on PostgreSQL, and depends on how the JSON is serialized.

## Decision

The choices of a select attribute are read once per attribute code and matched in PHP; the query
then filters on the identifiers of the matching values.

## Consequences

- Every value of a filtered select attribute is read, once per request and code: an accepted
  cost on a large catalog.
- The filters behave the same on MySQL, MariaDB and PostgreSQL.

## Related

- [0008. Advanced filters are counted subqueries, with caps on codes and values](0008-advanced-filters-are-counted-subqueries-with-caps.md)
- [0016. Conditions compare case-insensitively on every database](0016-conditions-compare-case-insensitively.md)
