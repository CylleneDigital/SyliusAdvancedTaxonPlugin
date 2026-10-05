# Contributing

Thanks for your interest in this plugin. Issues and pull requests are welcome.

**A security flaw is not reported through an issue**: see [SECURITY.md](SECURITY.md).

## Setting up the development environment

Fork `CylleneDigital/SyliusAdvancedTaxonPlugin` on GitHub, then clone your fork and keep the
original repository as `upstream`:

```bash
git clone git@github.com:<your-account>/SyliusAdvancedTaxonPlugin.git
cd SyliusAdvancedTaxonPlugin
git remote add upstream git@github.com:CylleneDigital/SyliusAdvancedTaxonPlugin.git
composer install
```

The plugin is exercised against Sylius through [`sylius/test-application`](https://github.com/Sylius/TestApplication)
(configured under `tests/TestApplication/`, including the `Taxon`, `Channel` and `TaxonImage`
entities that use the plugin traits).

Unit tests do not need a database. Integration, functional and Behat tests boot the
test-application kernel and need a database.

With PHP, a database and Node on the host, one command prepares the test application for the
environment in `APP_ENV`: it **drops and recreates the database**, runs the migrations, loads the
fixtures, builds the front end and runs `assets:install`:

```bash
composer run test-app-init
```

`make test-app-init` (Docker stack, below) is lighter: it creates the database if missing, runs the
migrations and `assets:install`, without fixtures or front-end build (`make test-app-frontend`).

### Database on the host

`tests/TestApplication/.env` points at `mysql://root@127.0.0.1/…` and `tests/TestApplication/.env.test`
(test environment) at `mysql://root:root@127.0.0.1/…`, both on the default port 3306. To use the
Compose MySQL below instead (published on host port **3307**, user `root`, empty password), create
`tests/TestApplication/.env.test.local` (gitignored):

```dotenv
DATABASE_URL=mysql://root@127.0.0.1:3307/cyllene_digital_sylius_advanced_taxon_plugin_%kernel.environment%
```

On MariaDB, declare a MySQL server version (`serverVersion=8.0.36`) and create the database in
`utf8mb4_unicode_ci`: on a database declared as MariaDB, DBAL 4 skips the Sylius core migrations,
and DBAL cannot introspect the default MariaDB 11 collation.

### Docker stack

Ephemeral stack: MySQL + PHP + nginx. `compose.override.yml` is gitignored: copy it from the dist
file (or let `make docker-up` create it). It has **no Chrome service**.

```bash
composer install                 # on the host, or: docker compose exec php composer install
make docker-up                   # copies compose.override.dist.yml if missing, then up -d
                                 # MySQL is published on host port 3307 (3306 is often taken)
make test-app-init               # database + migrations + assets:install (into test-application/public)
make test-app-frontend           # yarn build in vendor/sylius/test-application (admin and shop @ui)
docker compose exec -T php vendor/bin/behat --strict --no-interaction -f progress --tags='~@javascript'
make docker-down                 # stop and remove volumes when finished
```

`make behat-docker` runs **every** scenario, so its `@javascript` ones fail without a Chrome the PHP
container can reach; use the command above, or run the `@javascript` scenarios on the host.

### Dev shop

The test application loads the Sylius `default` fixture suite plus, from
`tests/TestApplication/config/packages/sylius_fixtures.yaml`, a second channel `B2B_WEB` and `fr_FR`
on `FASHION_WEB`, to compare the per-channel settings such as the mega menu:

```bash
vendor/bin/console sylius:fixtures:load default -n
```

Sylius picks the channel from the host name. `FASHION_WEB` takes `SYLIUS_FIXTURES_HOSTNAME`, which
defaults to `localhost` (`127.0.0.1` matches it too), and `B2B_WEB` takes `b2b.` + it: browsing on
`localhost`, as the Docker stack and the PHP built-in server do, needs no setting. Only for another
host name (a local domain behind a reverse proxy), set it **before** loading the fixtures, in
`tests/TestApplication/.env.dev.local` (`.env.test.local` with the Docker stack, which runs in the
test environment):

```dotenv
SYLIUS_FIXTURES_HOSTNAME=shop.example.localhost
```

In debug mode, `?_channel_code=B2B_WEB` also switches the shop to the second channel (kept in a
cookie; `?_channel_code=FASHION_WEB` to come back).

The plugin settings are then made per taxon in the back office (`Catalog > Taxons`) and per channel
for the mega menu.

### Behat

47 scenarios under `features/`, one suite per feature (`tests/Behat/Resources/suites.php`). 42 use
the Mink `symfony` session (no browser); 5 are tagged `@javascript` and drive a real Chrome: the
icon picker, the media rows added in the taxon form and the conditions (preview, save, refusal).

Scenarios without a browser:

```bash
vendor/bin/behat --strict --no-interaction -f progress --tags='~@javascript'
```

`@javascript` scenarios need, as configured in `behat.dist.php`:

1. Chrome headless with remote debugging on `127.0.0.1:9222`:

   ```bash
   google-chrome --headless=new --remote-debugging-port=9222 --no-sandbox
   ```

2. the test application served **in the test environment** at `BEHAT_BASE_URL`
   (`http://127.0.0.1:8080/` in `tests/TestApplication/.env`), after `make test-app-init` /
   `make test-app-frontend` or their host equivalents:

   ```bash
   APP_ENV=test php -d variables_order=EGPCS -S 127.0.0.1:8080 -t vendor/sylius/test-application/public
   ```

   The Compose nginx also publishes port 8080: stop the Docker stack first, or serve on another
   port and set the same URL as `BEHAT_BASE_URL` in `tests/TestApplication/.env.test.local`.

Then run the whole suite:

```bash
vendor/bin/behat --strict --no-interaction -f progress
```

Failure screenshots and pages land in `etc/build/`. CI runs every scenario, `@javascript` included
([`SyliusLabs/BuildTestAppAction`](https://github.com/SyliusLabs/BuildTestAppAction) with
`e2e_js: "yes"` starts Chrome and the web server).

## Proposing a change

`main` is protected. Every change goes through a pull request from a fork, a one-line documentation
fix included, and the maintainers work the same way.

1. Branch off an up-to-date `main`, one topic per branch:

   ```bash
   git fetch upstream
   git switch -c fix/short-description upstream/main
   ```

2. Make the change, with its test (see [Conventions](#conventions)), and run the
   [checks](#what-has-to-pass-before-a-pull-request): they are the ones the CI runs.
3. Commit in **English**, conventional form (`feat:`, `fix:`, `refactor:`, `docs:`, `test:`, `ci:`,
   `chore:`), imperative mood, then push the branch to your fork:

   ```bash
   git commit -m "fix: keep the featured products in the tree order"
   git push -u origin fix/short-description
   ```

4. Open the pull request against `main` of `CylleneDigital/SyliusAdvancedTaxonPlugin` (GitHub
   offers *Compare & pull request* on your fork). Use the commit message as the title, and fill in
   the template: what the pull request does (why, what it changes for a shop, how you checked it)
   and the points to watch. Add a before /
   after screenshot for a visible change to the shop or the back office, and `Closes #<issue>` when
   it fixes an issue.
5. If `main` moves before the merge, rebase rather than merge it into your branch:

   ```bash
   git fetch upstream
   git rebase upstream/main
   git push --force-with-lease
   ```

Pushing to your fork runs nothing: the workflows start when the pull request is opened. On a first
contribution they also wait for a maintainer to approve the run, GitHub's default on public
repositories, not something you did wrong.

Two checks have to be green, and the branch up to date with `main`, before a pull request can be
merged:

| Check | What it covers |
|---|---|
| **`Build complete`** | Every job of the matrix (Sylius ~2.1 / ~2.2 / ~2.3 × PHP 8.3 to 8.5 × Symfony ^7.4 / ^8.0 on MySQL 8.4, within what each Sylius supports, plus MySQL 8.0, MariaDB 10.11 / 11.4 and PostgreSQL 15 to 17 on Sylius 2.3) passed the CI checks below |
| **`Composer audit`** | Known vulnerabilities in dependencies, on the newest resolution and on the Sylius 2.1 one |

## What has to pass before a pull request

The CI checks, in the order CI runs them (`.github/workflows/build.yaml`), with `APP_ENV=test` and a
database built from the migrations (`make test-app-init`):

```bash
composer validate --ansi --strict
vendor/bin/console lint:container
vendor/bin/console doctrine:schema:validate --skip-sync
vendor/bin/console doctrine:migrations:execute 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000' --down -n
vendor/bin/console doctrine:migrations:execute 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000' --up -n
sh tests/check-schema-drift.sh
vendor/bin/phpstan analyse --memory-limit=1G --no-progress
vendor/bin/ecs check --no-progress-bar
vendor/bin/console lint:twig templates
vendor/bin/phpunit --colors=always --testsuite=unit
vendor/bin/phpunit --colors=always --testsuite=non-unit
vendor/bin/behat --colors --strict --no-interaction -f progress
```

`tests/check-schema-drift.sh` is the migration / mapping drift check: it must print **nothing**.
Any SQL for a `cyllene_advanced_taxon_*` table, or for a column the plugin adds to `sylius_taxon`,
`sylius_channel` or `sylius_taxon_image`, means the migration and the entity mapping have diverged.
Run it, and the `--down` / `--up` replay, on MySQL or PostgreSQL: on MariaDB, DBAL introspection
reports every nullable column again, and fails on a MariaDB 11 table. PHPStan, ECS and the template
lint only run on the primary job (Sylius 2.3, PHP 8.4, Symfony 7.4, MySQL 8.4), which also measures
the code coverage. Before Sylius 2.3, the CI keeps `doctrine/orm` below 3.7 (Sylius 2.1 mappings fail
its validation) and Behat on 3 (Sylius 2.1 and 2.2 declare their steps in annotations, which Behat 4
no longer reads). Behat
prerequisites: [Behat](#behat).

Make targets: `make phpunit`, `make test-unit`, `make test-integration`, `make test-functional`, `make behat`,
`make behat-docker`, `make docker-up`, `make docker-down`, `make test-app-init`,
`make test-app-frontend`, `make lint-container`, `make phpstan`, `make ecs`.

`vendor/bin/ecs check --fix` fixes the style automatically. The standard is
[`sylius-labs/coding-standard`](https://github.com/Sylius-Labs/CodingStandard), the one used by
Sylius plugins, do not add personal rules to it.

## Conventions

- **PHPStan level `max`** on `src/` and `tests/` (`phpstan.neon`, test application entities
  included). Lowering the level or adding `ignoreErrors` needs a justification in the pull request.
- **ECS** via `sylius-labs/coding-standard` (`ecs.php`), on `src/`, `tests/` and the test
  application sources.
- **Tests are mandatory** for bug fixes: the test must fail before the fix. The queries
  (`ConditionalTaxonQueryBuilder`, `FacetProductQueryBuilder`, the grid query) are covered by
  integration tests on a real database; prefer a Behat scenario when the bug is visible in the shop
  or the back office.
- The plugin never replaces a Sylius model: new fields on `Taxon`, `Channel` or `TaxonImage` go into
  the matching trait and interface, and the migration.
- Services are defined explicitly in `config/services/`, without autowiring
  ([code map](docs/development/code-map.md#wiring)).
- Update **`UPGRADE.md`** when the public contract changes; the change itself is described in the
  pull request, which feeds the release notes. Public contract items are listed in
  [docs/architecture/public-contract.md](docs/architecture/public-contract.md); they only break in
  a major version.
- Do not commit customer data, shop secrets or pre-production URLs.
- The codebase is written in **English** (code, comments, commits).
- A map of the code is in [docs/development/code-map.md](docs/development/code-map.md); the test
  strategy in [docs/testing/tests.md](docs/testing/tests.md).

## What does not belong here

Shop-specific merchandising, custom themes and project data stay in the project that uses the
plugin. This repository only carries the Sylius wiring of the taxon pages, filters, conditional
taxons and menus.
