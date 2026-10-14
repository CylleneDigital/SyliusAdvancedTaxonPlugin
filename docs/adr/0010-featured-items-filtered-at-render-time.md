# 0010. Featured items are filtered for visibility at render time

- Status: accepted
- Date: 2026-10-14

## Context

A taxon features products and child taxons picked in the back office. Later on, a product can be
disabled or leave the channel, a taxon can be disabled or moved under another parent. Validating the
selection on save would not catch these later changes.

## Decision

The selection is stored as is and filtered when the shop renders it: disabled products, products
outside the current channel, disabled taxons and children moved elsewhere are left out.

## Consequences

- The back office keeps a selection the shop may partly hide.
- The filtering costs a query for the channels not loaded yet, not one per product.

## Related

- [0025. Twig functions are served by lazy runtimes](0025-twig-functions-served-by-lazy-runtimes.md)
