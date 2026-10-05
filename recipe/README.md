# Symfony Flex recipe

Recipe for `cyllene-digital/sylius-advanced-taxon-plugin`, written with the
[simplified recipe structure](https://symfony.com/doc/current/setup/flex_private_recipes.html) used
by [symfony/recipes-contrib](https://github.com/symfony/recipes-contrib). It is what turns:

```bash
composer require cyllene-digital/sylius-advanced-taxon-plugin
```

into a working installation instead of a package that has to be wired by hand.

## Layout

```
recipe/
└── cyllene-digital/
    └── sylius-advanced-taxon-plugin/
        └── 1.0/
            ├── manifest.json
            ├── post-install.txt
            └── config/
                ├── packages/cyllene_digital_sylius_advanced_taxon.yaml
                └── routes/cyllene_digital_sylius_advanced_taxon.yaml
```

`1.0` is the `{major}.{minor}` version of the plugin the recipe applies to, as required by the
recipes checker: a new version directory is added when a release changes what has to be installed,
and the previous one is kept while older plugin versions are supported.

## What the recipe does

| File | Effect on the host application |
| --- | --- |
| `manifest.json` | registers `CylleneDigitalSyliusAdvancedTaxonPlugin` in `config/bundles.php` for all environments, and copies the recipe `config/` directory into the application |
| `config/packages/cyllene_digital_sylius_advanced_taxon.yaml` | imports `@CylleneDigitalSyliusAdvancedTaxonPlugin/config/config.yaml`, which declares the Sylius `taxon`, `channel` and `taxon_image` model overrides and the plugin Twig Hooks, then declares the `cyllene_universe_slider_mobile` LiipImagine filter set and documents the optional `icon_libraries` setting |
| `config/routes/cyllene_digital_sylius_advanced_taxon.yaml` | registers the plugin admin and shop routes; it needs no explicit import since the Flex `Kernel` loads `config/routes/*.yaml` |
| `post-install.txt` | prints the steps Flex cannot perform: migrations, asset build, pictogram directory, background synchronization |

Doctrine mappings, migrations, services, translations and templates need no configuration: they are
registered by the plugin bundle and its DI extension.

## Testing locally

The recipe is applied by Flex itself, so the simplest check is to reproduce what Flex does on a
throwaway Sylius application:

1. create a fresh Sylius application (or a copy of an existing one) and require the plugin without
   Flex handling it (no recipe published yet, so nothing is applied automatically);
2. copy the recipe files where Flex would put them:

    ```bash
    cp -r recipe/cyllene-digital/sylius-advanced-taxon-plugin/1.0/config/. /path/to/app/config/
    ```

3. add the bundle to `config/bundles.php`, as the recipe `manifest.json` does:

    ```php
    CylleneDigital\SyliusAdvancedTaxonPlugin\CylleneDigitalSyliusAdvancedTaxonPlugin::class => ['all' => true],
    ```

4. run the steps printed by `post-install.txt`, then open a taxon in the back office.

Applying the recipe through Flex itself requires a recipe repository endpoint, described in
[How To Configure and Use Flex Private Recipe Repositories](https://symfony.com/doc/current/setup/flex_private_recipes.html).
Once recipes are installed, `composer recipes` lists them and `composer sync-recipes --force
cyllene-digital/sylius-advanced-taxon-plugin` re-applies one after a change.

## Publishing

1. fork `symfony/recipes-contrib`;
2. copy `recipe/cyllene-digital/` to the root of the fork, keeping the same paths;
3. open a pull request titled `Add recipe for cyllene-digital/sylius-advanced-taxon-plugin 1.0`.

The repository's checker validates the recipe (manifest keys, referenced files, `post-install.txt`)
and generates the compiled endpoint files, which is why this folder keeps only the simplified
structure and no generated JSON.

Recipes are considered "contrib": applications install them only when `extra.symfony.allow-contrib`
is enabled.
