# 0016. Conditions compare case-insensitively on every database

- Status: accepted
- Date: 2026-10-14

## Context

MySQL and MariaDB compare text case-insensitively with the usual collations, PostgreSQL does not:
the same condition would match different products depending on the database.

## Decision

Text comparisons of the conditions lower both sides.

## Consequences

- Case no longer makes a conditional taxon list different products on MySQL, MariaDB and PostgreSQL;
  accent sensitivity still follows the database collation.
- A condition cannot tell values apart by their case.

## Related

- [0009. No `LIKE` on JSON columns](0009-no-like-on-json-columns.md)
- [0017. Option conditions match the translated value, and `in` takes one value](0017-option-conditions-match-the-translated-value.md)
