import { describe, expect, test } from 'vitest';
// the library first: its modules import each other in a circle that only resolves when it is entered from its index
import '../../Resources/Private/Client/src/lib';
import { getListCollectionFromHydrationData } from '../../Resources/Private/Client/src/lib/hydration';
import cases from '../Fixtures/list-collection.json';

// The same cases `Unit/ListCollectionTest::agreesWithTheClientOnWhatTheWireShapeMeans` runs through the PHP
// `ListCollection`: what the server renders and what the client works with must be the same items.
describe('list collection read from what PHP ships', () => {
    test.each(cases)('$name', ({ wire, expected }) => {
        const collection = getListCollectionFromHydrationData(wire as never);

        const items = collection.items;
        expect(items.map(item => collection.getItemValue(item))).toEqual(expected.values);
        expect(items.map(item => collection.stringifyItem(item))).toEqual(expected.labels);
        expect(items.map(item => collection.getItemDisabled(item))).toEqual(expected.disabled);
        expect(
            collection
                .group()
                .map(([key, group]) => [key, group.map(item => collection.getItemValue(item))])
        ).toEqual(expected.groups);
    });
});
