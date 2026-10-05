/**
 * Index of the next row of a Symfony form collection.
 *
 * Counting the rows is not enough: once a row is removed, or when the form is rendered again after
 * a validation error with its submitted keys, the count points to an index already in use and the
 * new row would overwrite it. The counter starts after the highest index rendered, and only grows.
 */
export function nextCollectionIndex(holder, items, prototype, prototypeName) {
    if (holder.dataset.nextIndex === undefined) {
        const escapedName = prototypeName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const match = prototype.match(new RegExp(`name="([^"]*)\\[${escapedName}\\]`));
        let highest = -1;

        if (match) {
            const prefix = `${match[1]}[`;
            items.querySelectorAll('[name]').forEach((element) => {
                if (element.name.startsWith(prefix)) {
                    const index = parseInt(element.name.slice(prefix.length), 10);
                    if (!Number.isNaN(index)) {
                        highest = Math.max(highest, index);
                    }
                }
            });
        }

        holder.dataset.nextIndex = String(Math.max(highest + 1, items.children.length));
    }

    const index = Number(holder.dataset.nextIndex);
    holder.dataset.nextIndex = String(index + 1);

    return index;
}
