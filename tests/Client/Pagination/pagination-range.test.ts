import { afterEach, describe, expect, test } from 'vitest';
import { Pagination } from '../../../Resources/Private/Primitives/Pagination/Pagination';
import cases from '../../Fixtures/pagination-range.json';

let pagination: Pagination | undefined;

afterEach(() => {
    pagination?.destroy();
    pagination = undefined;
});

// The same cases `Unit/PaginationRangeTest` runs through the PHP port: the pages the server renders
// are the pages the machine takes over.
describe('pages of the machine', () => {
    test.each(cases)('$name', ({ page, totalPages, siblingCount, boundaryCount, expected }) => {
        pagination = new Pagination({
            id: 'range',
            count: totalPages,
            defaultPageSize: 1,
            defaultPage: page,
            siblingCount,
            boundaryCount,
        }).init();

        expect(
            pagination.api.pages.map(entry => (entry.type === 'page' ? entry.value : null))
        ).toEqual(expected);
    });
});
