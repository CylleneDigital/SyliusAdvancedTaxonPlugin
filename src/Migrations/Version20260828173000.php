<?php

declare(strict_types=1);

namespace CylleneDigital\SyliusAdvancedTaxonPlugin\Migrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates the Advanced Taxon schema (taxon and channel columns, media fields, join tables,
 * translation tables and conditional facet conditions).
 *
 * The target schema is built on top of the introspected one, then diffed and rendered by Doctrine
 * DBAL. Because DBAL generates the DDL per platform, no SQL is written by hand: boolean/text types,
 * auto-increment columns, table options and index declaration are handled for MySQL, MariaDB and
 * PostgreSQL alike. Missing columns and tables are only added when absent, so the migration is
 * replayable.
 */
final class Version20260828173000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Initial schema for Advanced Taxon plugin (taxon, channel, media, translations, virtual conditions, and advanced filters).';
    }

    public function up(Schema $schema): void
    {
        foreach ($this->diffSql($schema, $this->targetSchema($schema)) as $statement) {
            $this->addSql($statement);
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        foreach ($this->diffSql($schema, $this->reducedSchema($schema)) as $statement) {
            $this->addSql($statement);
        }
    }

    private function targetSchema(Schema $schema): Schema
    {
        $toSchema = clone $schema;

        foreach ($this->addedColumns() as $tableName => $columns) {
            if (!$toSchema->hasTable($tableName)) {
                continue;
            }

            $table = $toSchema->getTable($tableName);

            foreach ($columns as $columnName => [$type, $options]) {
                if (!$table->hasColumn($columnName)) {
                    $table->addColumn($columnName, $type, $options);
                }
            }
        }

        $this->createPluginTables($toSchema);

        return $toSchema;
    }

    private function reducedSchema(Schema $schema): Schema
    {
        $toSchema = clone $schema;

        foreach ($this->addedColumns() as $tableName => $columns) {
            if (!$toSchema->hasTable($tableName)) {
                continue;
            }

            $table = $toSchema->getTable($tableName);

            foreach (array_keys($columns) as $columnName) {
                if ($table->hasColumn($columnName)) {
                    $table->dropColumn($columnName);
                }
            }
        }

        foreach ($this->pluginTableNames() as $tableName) {
            if ($toSchema->hasTable($tableName)) {
                $toSchema->dropTable($tableName);
            }
        }

        foreach ($this->sequenceNames() as $sequenceName) {
            if ($toSchema->hasSequence($sequenceName)) {
                $toSchema->dropSequence($sequenceName);
            }
        }

        return $toSchema;
    }

    /**
     * On PostgreSQL the ids of the plugin entities come from a sequence, as Sylius configures it
     * (identity_generation_preferences): an identity column would leave a drift against the mapping,
     * and the ORM would ask for a sequence that does not exist.
     */
    private function idsComeFromSequences(): bool
    {
        return $this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform;
    }

    /**
     * @return list<string>
     */
    private function sequenceNames(): array
    {
        return [
            'cyllene_advanced_taxon_facet_condition_id_seq',
            'cyllene_advanced_taxon_featured_items_translation_id_seq',
            'cyllene_advanced_taxon_image_translation_id_seq',
        ];
    }

    /**
     * @return list<string>
     */
    private function diffSql(Schema $fromSchema, Schema $toSchema): array
    {
        $comparator = $this->connection->createSchemaManager()->createComparator();

        return $this->connection->getDatabasePlatform()->getAlterSchemaSQL(
            $comparator->compareSchemas($fromSchema, $toSchema),
        );
    }

    /**
     * Columns added on top of the Sylius tables, grouped by table.
     *
     * @return array<string, array<string, array{0: string, 1: array<string, mixed>}>>
     */
    private function addedColumns(): array
    {
        return [
            'sylius_taxon' => [
                'color' => [Types::STRING, ['length' => 32, 'notnull' => false]],
                'icon' => [Types::STRING, ['length' => 1024, 'notnull' => false]],
                'icon_type' => [Types::STRING, ['length' => 255, 'notnull' => false]],
                'conditional' => [Types::BOOLEAN, ['default' => false]],
                'is_slider' => [Types::BOOLEAN, ['default' => false]],
                'is_featured_products_active' => [Types::BOOLEAN, ['default' => false]],
                'featured_products_position' => [Types::STRING, ['length' => 32, 'default' => 'before_filters']],
                'media_display_mode_top' => [Types::STRING, ['length' => 32, 'default' => 'stacked']],
                'media_display_mode_bottom' => [Types::STRING, ['length' => 32, 'default' => 'stacked']],
                'media_display_mode_left' => [Types::STRING, ['length' => 32, 'default' => 'stacked']],
                'media_display_mode_product' => [Types::STRING, ['length' => 32, 'default' => 'products_end']],
                'media_display_mode_featured' => [Types::STRING, ['length' => 32, 'default' => 'stacked']],
                'include_children_products' => [Types::BOOLEAN, ['default' => false]],
                'advanced_filters_enabled' => [Types::BOOLEAN, ['default' => false]],
                'is_universe' => [Types::BOOLEAN, ['default' => false]],
                'universe_background_use_taxon_color' => [Types::BOOLEAN, ['default' => false]],
                'show_customization_in_menu' => [Types::BOOLEAN, ['default' => true]],
                'show_customization_on_taxon_page' => [Types::BOOLEAN, ['default' => true]],
                'show_customization_in_breadcrumbs' => [Types::BOOLEAN, ['default' => true]],
                'universe_title_background_enabled' => [Types::BOOLEAN, ['default' => false]],
            ],
            'sylius_channel' => [
                'has_mega_menu' => [Types::BOOLEAN, ['default' => false]],
            ],
            'sylius_taxon_image' => [
                'title' => [Types::STRING, ['length' => 255, 'notnull' => false]],
                'url' => [Types::STRING, ['length' => 255, 'notnull' => false]],
                'description' => [Types::TEXT, ['notnull' => false]],
                'position' => [Types::INTEGER, ['default' => 0]],
                'show_card_text' => [Types::BOOLEAN, ['default' => true]],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function pluginTableNames(): array
    {
        return [
            'cyllene_advanced_taxon_facet_condition',
            'cyllene_advanced_taxon_featured_items_translation',
            'cyllene_advanced_taxon_image_translation',
            'cyllene_advanced_taxon_featured_children',
            'cyllene_advanced_taxon_featured_products',
        ];
    }

    private function createPluginTables(Schema $schema): void
    {
        if ($this->idsComeFromSequences()) {
            foreach ($this->sequenceNames() as $sequenceName) {
                if (!$schema->hasSequence($sequenceName)) {
                    $schema->createSequence($sequenceName);
                }
            }
        }

        if (!$schema->hasTable('cyllene_advanced_taxon_featured_children')) {
            $featuredChildren = $schema->createTable('cyllene_advanced_taxon_featured_children');
            $featuredChildren->addColumn('taxon_id', Types::INTEGER);
            $featuredChildren->addColumn('featured_taxon_id', Types::INTEGER);
            $featuredChildren->setPrimaryKey(['taxon_id', 'featured_taxon_id']);
            $featuredChildren->addIndex(['taxon_id']);
            $featuredChildren->addIndex(['featured_taxon_id']);
            $featuredChildren->addForeignKeyConstraint('sylius_taxon', ['taxon_id'], ['id'], ['onDelete' => 'CASCADE']);
            $featuredChildren->addForeignKeyConstraint('sylius_taxon', ['featured_taxon_id'], ['id'], ['onDelete' => 'CASCADE']);
            $this->applyMySqlTableOptions($featuredChildren);
        }

        if (!$schema->hasTable('cyllene_advanced_taxon_featured_products')) {
            $featuredProducts = $schema->createTable('cyllene_advanced_taxon_featured_products');
            $featuredProducts->addColumn('taxon_id', Types::INTEGER);
            $featuredProducts->addColumn('product_id', Types::INTEGER);
            $featuredProducts->setPrimaryKey(['taxon_id', 'product_id']);
            $featuredProducts->addIndex(['taxon_id']);
            $featuredProducts->addIndex(['product_id']);
            $featuredProducts->addForeignKeyConstraint('sylius_taxon', ['taxon_id'], ['id'], ['onDelete' => 'CASCADE']);
            $featuredProducts->addForeignKeyConstraint('sylius_product', ['product_id'], ['id'], ['onDelete' => 'CASCADE']);
            $this->applyMySqlTableOptions($featuredProducts);
        }

        if (!$schema->hasTable('cyllene_advanced_taxon_facet_condition')) {
            $facetCondition = $schema->createTable('cyllene_advanced_taxon_facet_condition');
            $facetCondition->addColumn('id', Types::INTEGER, ['autoincrement' => !$this->idsComeFromSequences()]);
            $facetCondition->addColumn('taxon_id', Types::INTEGER);
            $facetCondition->addColumn('condition_type', Types::STRING, ['length' => 32]);
            $facetCondition->addColumn('operator', Types::STRING, ['length' => 32]);
            $facetCondition->addColumn('reference_code', Types::STRING, ['length' => 255, 'notnull' => false]);
            $facetCondition->addColumn('value', Types::STRING, ['length' => 255, 'notnull' => false]);
            $facetCondition->addColumn('position', Types::INTEGER);
            $facetCondition->setPrimaryKey(['id']);
            $facetCondition->addIndex(['taxon_id']);
            $facetCondition->addForeignKeyConstraint('sylius_taxon', ['taxon_id'], ['id'], ['onDelete' => 'CASCADE']);
            $this->applyMySqlTableOptions($facetCondition);
        }

        if (!$schema->hasTable('cyllene_advanced_taxon_featured_items_translation')) {
            $featuredItems = $schema->createTable('cyllene_advanced_taxon_featured_items_translation');
            $featuredItems->addColumn('id', Types::INTEGER, ['autoincrement' => !$this->idsComeFromSequences()]);
            $featuredItems->addColumn('taxon_id', Types::INTEGER);
            $featuredItems->addColumn('locale', Types::STRING, ['length' => 255]);
            $featuredItems->addColumn('featured_products_title', Types::STRING, ['length' => 255, 'notnull' => false]);
            $featuredItems->addColumn('featured_products_description', Types::TEXT, ['notnull' => false]);
            $featuredItems->addColumn('featured_children_title', Types::STRING, ['length' => 255, 'notnull' => false]);
            $featuredItems->addColumn('featured_children_description', Types::TEXT, ['notnull' => false]);
            $featuredItems->addColumn('universe_page_title', Types::STRING, ['length' => 255, 'notnull' => false]);
            $featuredItems->addColumn('universe_page_description', Types::TEXT, ['notnull' => false]);
            $featuredItems->addColumn('universe_featured_products_title', Types::STRING, ['length' => 255, 'notnull' => false]);
            $featuredItems->addColumn('universe_featured_products_description', Types::TEXT, ['notnull' => false]);
            $featuredItems->setPrimaryKey(['id']);
            $featuredItems->addIndex(['taxon_id']);
            $featuredItems->addUniqueIndex(['taxon_id', 'locale'], 'featured_items_translation_uniq');
            $featuredItems->addForeignKeyConstraint('sylius_taxon', ['taxon_id'], ['id'], ['onDelete' => 'CASCADE']);
            $this->applyMySqlTableOptions($featuredItems);
        }

        if (!$schema->hasTable('cyllene_advanced_taxon_image_translation')) {
            $imageTranslation = $schema->createTable('cyllene_advanced_taxon_image_translation');
            $imageTranslation->addColumn('id', Types::INTEGER, ['autoincrement' => !$this->idsComeFromSequences()]);
            $imageTranslation->addColumn('translatable_id', Types::INTEGER);
            $imageTranslation->addColumn('locale', Types::STRING, ['length' => 255]);
            $imageTranslation->addColumn('title', Types::STRING, ['length' => 255, 'notnull' => false]);
            $imageTranslation->addColumn('description', Types::TEXT, ['notnull' => false]);
            $imageTranslation->setPrimaryKey(['id']);
            $imageTranslation->addIndex(['translatable_id']);
            $imageTranslation->addUniqueIndex(['translatable_id', 'locale'], 'image_translation_uniq');
            $imageTranslation->addForeignKeyConstraint('sylius_taxon_image', ['translatable_id'], ['id'], ['onDelete' => 'CASCADE']);
            $this->applyMySqlTableOptions($imageTranslation);
        }
    }

    private function applyMySqlTableOptions(Table $table): void
    {
        if (!$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            return;
        }

        $table->addOption('charset', 'utf8mb4');
        $table->addOption('collation', 'utf8mb4_unicode_ci');
    }
}
