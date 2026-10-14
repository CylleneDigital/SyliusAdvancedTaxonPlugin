# 0002. A universe is kept out of main taxons by a validation constraint, not a form override

- Status: accepted
- Date: 2026-10-14

## Context

A universe page lists child taxons, not products. A product whose main taxon is a universe would get
a breadcrumb and a canonical URL pointing to a page where it does not appear. Filtering the choices
of the main taxon field of the product form would only protect the back office, and replacing a core
field leaves the loading order of the form extensions decide which definition wins, losing the
native options of the field.

## Decision

A validation constraint on the product, in the `sylius` group, rejects a universe as main taxon. The
product form is not touched.

## Consequences

- The rule holds wherever the product is validated in the `sylius` group: the back office and the
  API.
- A universe still appears in the choices of the field; picking it shows a validation error.
- Code that saves a product without validating it, such as the Sylius fixtures, is not protected.
