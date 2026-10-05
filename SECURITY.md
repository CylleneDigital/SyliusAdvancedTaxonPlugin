# Security policy

This plugin runs in both the back office and the storefront: it stores taxon customization, media
metadata and uploaded pictograms, renders them in Twig, and sanitizes the destination URL of every
media before it reaches an `href`. A flaw here would most likely be a template-injection, file-upload
or stored-XSS issue - but please report it privately all the same.

## Reporting a vulnerability

**Do not open a public issue.** Use either of the two private channels:

- [GitHub security advisory](https://github.com/CylleneDigital/AdvancedTaxonPlugin/security/advisories/new)
  ("Report a vulnerability")
- email to sylius@groupe-cyllene.com

Please include the plugin version, the Sylius and PHP versions, and the steps to reproduce.

## Response time

First response within **5 working days**. We keep you posted on the analysis, then on the fix and its
release date.

## Supported versions

| Version | Support |
|---|---|
| `1.x` | Bug and security fixes |

## Disclosure

Coordinated disclosure: the fix is released first, then the advisory. We are happy to credit the
reporter, unless they ask otherwise.
