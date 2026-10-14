# 0026. Help texts only where a field's effect is not obvious

- Status: accepted
- Date: 2026-10-14

## Context

The taxon form has many fields. Explanations repeated in the form would multiply the texts to
translate and repeat the user guide until both diverge. Tooltips hide the help, work poorly on touch
screens and need JavaScript.

## Decision

Help is a short text under the field, never a tooltip. It says what the field does when the label
does not, or what it expects; the explanations stay in the user guide, which is the reference.

## Consequences

- The help is visible without interaction, on any screen.
- A help text that grows into an explanation moves to the guide.

## Related

- [0027. Translation keys keep their namespaces](0027-translation-keys-keep-their-namespaces.md)
