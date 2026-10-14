# 0017. Option conditions match the translated value, and `in` takes one value

- Status: accepted
- Date: 2026-10-14

## Context

An option condition compares the translated value of an option value, in any locale, not its code,
and its `in` operator takes a single value. Changing either changes what stored conditions mean.

## Decision

Both are kept as accepted limitations, documented with the conditions.

## Consequences

- Renaming an option value in every locale breaks the conditions that use it: positive ones stop
  matching, negative ones stop excluding.
- Matching on codes, or several values for `in`, would be a new operator or a major version, not a
  change of the existing ones.

## Related

- [0016. Conditions compare case-insensitively on every database](0016-conditions-compare-case-insensitively.md)
