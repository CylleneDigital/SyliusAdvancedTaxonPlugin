# 0005. The plugin takes over the Sylius search form so it keeps the advanced filters

- Status: accepted
- Date: 2026-10-14

## Context

The search form of the Sylius product listing only sends the search text: submitting it dropped
the advanced filters, the sort, the limit and the view. Hidden fields added through the nested
search hook would land inside the input group and break its rounded corners. A Stimulus controller
copying the parameters would not work without JavaScript and adds code.

## Decision

The plugin replaces the search hookable of the listing filters with a form that sends the
current advanced filters, sort, limit and view back, and starts again from the first page. Its
clear button only removes the search.

## Consequences

- Searching inside a filtered listing keeps the filters, with or without JavaScript.
- A theme that customizes this form has to keep the hidden fields.

## Related

- [0004. Twig hooks are imported, not prepended](0004-twig-hooks-imported-not-prepended.md)
- [0006. The taxon listing is the Sylius grid](0006-taxon-listing-is-the-sylius-grid.md)
