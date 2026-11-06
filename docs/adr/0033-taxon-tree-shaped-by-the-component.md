# 0033. The taxon tree is shaped by the component, not filtered in the browser

- Status: accepted
- Date: 2026-11-06

## Context

The back office taxon form lists the whole catalog in a tree, so a shop holding many taxons opens
it as a wall of taxons. Filtering it as the shopkeeper types looks like a browser job, until the
tree is looked at: a Sylius live component builds it, and a virtualized widget draws it, keeping
only the rows on screen in the DOM. That widget belongs to a Stimulus controller of the
application, in an application of its own, which the plugin entrypoint cannot reach. Filtering from
the browser would mean reaching into a controller the plugin does not own, or re-rendering rows
that are not there.

## Decision

The plugin renders the tree through a component extending the Sylius one, which only shapes the
tree data: it keeps the taxons matching the searched term with their ancestors and their own
taxons, and it says with each taxon whether the tree shows it open. The search field is a live
model of that component, so typing re-renders the data, and the template turns off the widget
option that would otherwise open everything. The rows, the actions and the Stimulus controller stay
the Sylius ones.

## Consequences

- Searching and folding take the same path: the component decides both, and the folding follows
  the search without a script of the plugin.
- A tree too large to read is folded when no term is searched, and opened on the branches a search
  returns; the size from which it folds is a decision of the component, not of the template.
- The hookable rendering the tree changes hands: a project that overrode the template it replaces
  has to move its override, as the upgrade guide says.
- The tree data travels with the page and with each re-render, so the payload grows with the
  catalog: a tree of thousands of taxons is a page of hundreds of kilobytes.
- A project reducing the payload cannot do it from the template alone, since the open state comes
  with the data.

## Related

- [0006. The taxon listing is the Sylius grid](0006-taxon-listing-is-the-sylius-grid.md)
- [0024. No script in the templates, behaviour in Stimulus controllers](0024-no-script-in-templates.md)
