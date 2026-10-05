# Migrations

## The migration

| Element | Value |
|---------|-------|
| File | `src/Migrations/Version20260828173000.php` |
| Class | `CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000` |
| Description | "Initial schema for Advanced Taxon plugin (taxon, channel, media, translations, virtual conditions, and advanced filters)." |

It is the only migration of the plugin. It adds 20 columns to `sylius_taxon`, 1 to `sylius_channel`,
5 to `sylius_taxon_image`, and creates the five `cyllene_advanced_taxon_*` tables (plus, on
PostgreSQL, the three id sequences). Inventory: [Schema](schema.md).

`v1.0.0` shipped this migration: it is never changed again, a schema change is a new migration.

## Strategy

No SQL is written by hand. The migration works on DBAL `Schema` objects:

1. `up()` clones the introspected schema, adds the missing columns and the missing tables to the
   clone (`targetSchema()`), then lets DBAL compare both schemas and render the `ALTER` / `CREATE`
   statements for the current platform (`getAlterSchemaSQL()`).
2. `down()` does the same towards a reduced schema (`reducedSchema()`): the added columns and the
   plugin tables are removed when present.

Consequences:

| Property | How |
|----------|-----|
| Adds only what is missing | A column is added only if `!$table->hasColumn()`, a table only if `!$schema->hasTable()` |
| Replayable | Running `up()` on a schema that already has everything renders no statement |
| Portable | Types are `Types::STRING`, `BOOLEAN`, `INTEGER`, `TEXT`; booleans, `TEXT`, auto-increment ids and index declarations are rendered by DBAL for MySQL, MariaDB and PostgreSQL |
| PostgreSQL ids | On `PostgreSQLPlatform`, the ids are plain integer columns fed by the sequences `cyllene_advanced_taxon_facet_condition_id_seq`, `cyllene_advanced_taxon_featured_items_translation_id_seq` and `cyllene_advanced_taxon_image_translation_id_seq`, as the ORM expects with the Sylius `identity_generation_preferences`: identity columns would leave a drift and an ORM asking for missing sequences |
| Same names as the ORM | Indexes and foreign keys are left unnamed, so DBAL names them with the algorithm the ORM uses; the two unique constraints carry the mapping names (`featured_items_translation_uniq`, `image_translation_uniq`) |
| MySQL table options | On a MySQL-family platform (`AbstractMySQLPlatform`), the plugin tables get `charset utf8mb4`, `collation utf8mb4_unicode_ci` |
| Sylius tables required | Columns are only added to `sylius_taxon`, `sylius_channel`, `sylius_taxon_image` when these tables exist; the foreign keys reference `sylius_taxon`, `sylius_product`, `sylius_taxon_image` |

`down()` drops the five plugin tables and the 26 added columns, with their data, and the three
sequences on PostgreSQL.

The CI runs the migration on MySQL, MariaDB and PostgreSQL 15, 16 and 17, and the `--down` / `--up`
replay and the drift check on MySQL and PostgreSQL: with the MySQL platform DBAL uses on MariaDB, it
cannot introspect a MariaDB 11 table (default collation) and reports every nullable column again.

## Registration

The migrations path is registered by the DI extension through Sylius
`PrependDoctrineMigrationsTrait`:

| Element | Value |
|---------|-------|
| Namespace | `CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations` |
| Directory | `@CylleneDigitalSyliusAdvancedTaxonPlugin/src/Migrations` |
| Executed after | `Sylius\Bundle\CoreBundle\Migrations` (via `sylius_labs_doctrine_migrations_extra`) |

The trait does nothing when `doctrine_migrations` or `sylius_labs_doctrine_migrations_extra` is not
registered, or when the container parameter `sylius_core.prepend_doctrine_migrations` is `false`. An
application that disables it registers the path itself:

```yaml
# config/packages/doctrine_migrations.yaml
doctrine_migrations:
    migrations_paths:
        'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations': '@CylleneDigitalSyliusAdvancedTaxonPlugin/src/Migrations'
```

Then, as for any Sylius plugin:

```bash
bin/console doctrine:migrations:migrate
```

## Dependency on the Sylius migrations

The plugin migration runs after the Sylius core migrations, since it alters and references Sylius
tables. If the Sylius tables do not exist when it runs, it adds no column to them.

### MariaDB with DBAL 4

The Sylius core migrations extend `Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration`,
which skips them unless the platform is `MySQLPlatform`. With DBAL 4, the MariaDB platform no longer
extends it: the Sylius migrations are marked as executed but skipped, and the symptom is a schema
holding only the `cyllene_advanced_taxon_*` tables.

On MariaDB, declare a MySQL server version and create the database in `utf8mb4_unicode_ci`
([CONTRIBUTING.md](../../CONTRIBUTING.md#database-on-the-host)):

```dotenv
DATABASE_URL="mysql://app:app@127.0.0.1:3306/cyllene_digital_sylius_advanced_taxon_plugin_%kernel.environment%?charset=utf8mb4&serverVersion=8.0.36"
```

## Drift check

The CI builds the database from the migrations (`APP_ENV=test`), then runs
([CONTRIBUTING.md](../../CONTRIBUTING.md#what-has-to-pass-before-a-pull-request)):

```bash
vendor/bin/console doctrine:schema:validate --skip-sync
vendor/bin/console doctrine:migrations:execute 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000' --down -n
vendor/bin/console doctrine:migrations:execute 'CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations\Version20260828173000' --up -n
sh tests/check-schema-drift.sh
```

- the `--down` / `--up` replay exercises `down()` and proves it leaves nothing that `up()` would
  collide with;
- the last command must print **nothing**: any SQL left for a `cyllene_advanced_taxon_*` table, or
  for a column the plugin adds to `sylius_taxon`, `sylius_channel` or `sylius_taxon_image`, means
  the migration and the mapping have diverged. The rest of the Sylius schema is left out so that a
  core drift does not mask it. A column added to a Sylius table goes into the script's list too.

The CI runs the replay and the check on MySQL and PostgreSQL, not on MariaDB (see above).

## Changing the schema

1. Change the mapping in the trait or entity.
2. Change `addedColumns()` or `createPluginTables()` in the migration (before `v1.0.0`), or add a
   migration using the same DBAL `Schema` approach (after).
3. Run the drift check above on MySQL and PostgreSQL.
4. Write the change in [UPGRADE.md](../../UPGRADE.md) when it affects existing data or the
   [public contract](../architecture/public-contract.md#database).
