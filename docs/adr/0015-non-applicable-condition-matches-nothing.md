# 0015. A condition that cannot apply matches nothing

- Status: accepted
- Date: 2026-10-14

## Context

A stored condition can be or become not applicable: an operator its type does not accept (written by
the API or an import), a missing value, an attribute, option or taxon that was deleted. Ignoring it
would widen the taxon, up to the whole catalog when it was the only condition.

## Decision

Conditional taxons fail closed: a condition that cannot apply makes the taxon match nothing,
negative operators included. A branch the applicability check makes unreachable throws instead of
skipping the condition.

## Consequences

- A broken condition empties the taxon instead of flooding it: visible, and harmless.
- The validation rejects what it can on save (wrong operator, missing reference or value, unknown or
  self reference); what breaks later is caught by the query.

## Related

- [0011. Conditional taxons are materialized as product-taxon links](0011-conditional-taxons-materialized-as-product-taxon-links.md)
- [0018. The condition preview mirrors the synchronization](0018-condition-preview-mirrors-the-synchronization.md)
