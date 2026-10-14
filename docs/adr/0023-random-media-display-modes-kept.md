# 0023. The random media display modes are kept

- Status: accepted
- Date: 2026-10-14

## Context

Some media zones offer a random display mode (shuffled media, a random spotlight, media inserted
at random places in the listing). A page that changes on every view cannot be cached as such by an
HTTP cache.

## Decision

The random modes are kept: they are a merchandising feature, chosen per taxon. Behind an HTTP
cache, the draw is frozen for the lifetime of each cache entry.

## Consequences

- A shop behind a cache gets a draw per cache entry, not per view.

## Related

- [0003. Plugin media zones replace the native taxon Images section](0003-media-zones-replace-the-native-images-section.md)
