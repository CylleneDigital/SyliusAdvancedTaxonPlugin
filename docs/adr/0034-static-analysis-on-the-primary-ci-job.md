# 0034. Static analysis and coverage run on the primary CI job only

- Status: accepted
- Date: 2026-10-14

## Context

The CI matrix runs every supported combination of Sylius, Symfony, PHP and database. Static
analysis, the coding standard and the template lint depend neither on the database nor on the PHP
version under test; coverage is only meaningful once.

## Decision

They run on the primary job only. The other jobs run the tests.

## Consequences

- A faster matrix, with one place to read the analysis and the coverage.
- A problem that only static analysis on another version would show is not caught.

## Related

- [0033. A workaround for an upstream bug never constrains the shops](0033-upstream-workarounds-never-constrain-shops.md)
- [0035. The contributor stack ships a Chrome service](0035-contributor-stack-ships-chrome.md)
