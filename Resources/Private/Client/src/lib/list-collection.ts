import { ListCollection, type CollectionItem } from '@zag-js/collection';

// A key names a property of the item, or a path of them joined by dots (`meta.id`).
// Keep in sync with: ListCollection::getFromKey() of PHP, which renders the same collection on the server.
function getByPath(item: unknown, path: string): any {
    return path.split('.').reduce<any>((value, key) => value?.[key], item);
}

function asString(value: unknown): string {
    return value == null ? '' : String(value);
}

export function getListCollectionFromHydrationData<T extends CollectionItem>(hydrationCollection: {
    items: T[];
    itemToValueKey?: string | null;
    itemToStringKey?: string | null;
    isItemDisabledKey?: string | null;
    groupByKey?: string | null;
    groupSort?: 'asc' | 'desc' | Array<string> | null;
}): ListCollection<T> {
    if (hydrationCollection instanceof ListCollection) {
        return hydrationCollection;
    }

    const { itemToValueKey, itemToStringKey, isItemDisabledKey, groupByKey, groupSort } =
        hydrationCollection;

    return new ListCollection<T>({
        items: hydrationCollection.items,
        // Zag types these as returning a `string` but never coerces. Fluid turns a quoted `'1'` into
        // an int, and the PHP collection reads it back as the string `'1'`, so do the same here.
        itemToValue: (item: any) => asString(getByPath(item, itemToValueKey ?? 'value')),
        itemToString: (item: any) => {
            const label = getByPath(item, itemToStringKey ?? 'label');
            // a plain `{value}` item needs no label, a configured `itemToStringKey` never falls back
            return asString(
                label ?? (itemToStringKey ? '' : getByPath(item, itemToValueKey ?? 'value'))
            );
        },
        isItemDisabled: isItemDisabledKey
            ? (item: any) => getByPath(item, isItemDisabledKey)
            : undefined,
        groupBy: groupByKey ? (item: any) => getByPath(item, groupByKey) : undefined,
        groupSort: groupSort ?? undefined,
    });
}
