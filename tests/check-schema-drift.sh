#!/bin/sh
# Migration / mapping drift check: prints the SQL left to apply on the plugin schema and fails, or
# prints nothing. It covers the plugin tables and the columns the plugin adds to the Sylius tables,
# and leaves out the rest of the Sylius schema: a core drift is not ours to fix and would mask ours.
# Run it on MySQL or PostgreSQL: on MariaDB, DBAL introspection reports every nullable column again.
set -eu

drift="$(vendor/bin/console doctrine:schema:update --dump-sql "$@" | grep -iE \
    -e 'cyllene_advanced_taxon' \
    -e 'sylius_taxon .*\b(color|icon|icon_type|conditional|is_slider|is_featured_products_active|featured_products_position|media_display_mode_[a-z]+|include_children_products|advanced_filters_enabled|is_universe|universe_[a-z_]+|show_customization_[a-z_]+)\b' \
    -e 'sylius_channel .*\bhas_mega_menu\b' \
    -e 'sylius_taxon_image .*\b(title|url|description|position|show_card_text)\b' \
    || true)"

if [ -n "${drift}" ]; then
    echo "${drift}"
    exit 1
fi
