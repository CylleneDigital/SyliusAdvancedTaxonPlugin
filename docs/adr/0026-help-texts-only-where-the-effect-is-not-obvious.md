# 0026. Help texts only where a field's effect is not obvious

- Status: accepted
- Date: 2026-10-14

## Context

The taxon form has many fields. A help text on each would load the form, multiply the texts to
translate and repeat the user guide until both diverge. Tooltips hide the help, work poorly on
touch screens and need JavaScript.

## Decision

A field gets a one-line help text only when its effect is not obvious: a side effect, a scope
narrower than expected, an expected format. The user guide stays the reference.

## Consequences

- A new field gets a help text only if it meets that bar.
- The form stays readable; the details are in the guide.

## Related

- [0027. Translation keys keep their namespaces](0027-translation-keys-keep-their-namespaces.md)
