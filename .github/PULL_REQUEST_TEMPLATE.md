## What this pull request does



## Points to watch

- [ ] Tests pass (`vendor/bin/phpunit`, `vendor/bin/behat --strict`)
- [ ] PHPStan and ECS are green
- [ ] If the schema changed: the migration is updated, and `sh tests/check-schema-drift.sh` prints nothing (MySQL or PostgreSQL)
- [ ] `UPGRADE.md` is up to date if the public contract changes (traits and interfaces, Twig hooks and functions, grid query)
- [ ] No customer data, shop secrets or pre-production URLs appear in the code, the tests or this description
