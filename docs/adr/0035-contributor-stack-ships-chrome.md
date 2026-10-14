# 0035. The contributor stack ships a Chrome service

- Status: accepted
- Date: 2026-10-14

## Context

The Behat Chrome driver asks Chrome for its version as soon as it is built, so Behat does not
start without a Chrome, even for the scenarios that do not use a browser. A contributor stack that
cannot run Behat does not help.

## Decision

The versioned Docker stack contributors use has a headless Chrome service, sharing the network of
the PHP service, so Behat reaches it the way its configuration expects.

## Consequences

- The whole Behat suite runs in the contributor stack.
- The stack differs from the other plugins' on this point.

## Related

- [0034. Static analysis and coverage run on the primary CI job only](0034-static-analysis-on-the-primary-ci-job.md)
