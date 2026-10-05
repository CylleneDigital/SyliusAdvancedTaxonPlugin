# Upgrade guide

This file lists, one major version at a time, what has to change in a project already using the
plugin.

The plugin extends the Sylius taxon, channel and taxon image models through traits, overrides admin
and shop Twig hooks and takes over the query of the shop product grid: a compatibility break (a
renamed trait method, a moved Twig hook, a changed Twig function) can silently change what a taxon
page lists or renders. Every break must therefore be written here before it is released, together
with the exact steps to carry out.

`v1.0.0` is the first release: there is nothing to upgrade from yet.
