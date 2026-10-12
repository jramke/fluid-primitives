import { beforeEach, describe, expect, test } from 'vitest';
// the library first: its modules import each other in a circle that only resolves when it is entered from its index
import { ComponentHydrator } from '../../Resources/Private/Client/src/lib';
import { destroyComponentsWithin } from '../../Resources/Private/Client/src/lib/hydration';
import { Template } from '../../Resources/Private/Client/src/lib/template';

const wrapper = (name: string, inner: string) =>
    `<div data-portal="${name}" style="display: contents">${inner}</div>`;

function render(rowContent: string, extra = '') {
    document.body.innerHTML = `
        <div id="page">
            <template data-portal-container="sidebar"></template>
            <ul data-field-array-item-group="fa1"></ul>
            <template data-field-array-item-template="fa1">
                <li data-field-array-item="fa1">${rowContent}</li>
            </template>
        </div>${extra}`;
}

const hydrator = new ComponentHydrator('fieldArray', 'fa1');
const addRow = () => {
    const clone = new Template(hydrator, 'itemTemplate');
    document.querySelector('ul')!.append(clone);
    return clone.root;
};

beforeEach(() => {
    window.FluidPrimitives = undefined as never;
});

describe('portals of a cloned template', () => {
    test('go to the end of the body, or after the container of their name', () => {
        render(
            `<span id="trigger">Open</span>${wrapper('default', '<p id="popup">Popup</p>')}${wrapper('sidebar', '<p id="side">Side</p>')}`
        );

        const row = addRow();

        expect(row.querySelector('#trigger')).not.toBeNull();
        expect(row.querySelector('#popup')).toBeNull();
        expect(document.body.lastElementChild!.querySelector('#popup')).not.toBeNull();
        const container = document.querySelector('template[data-portal-container="sidebar"]')!;
        expect(container.nextElementSibling!.querySelector('#side')).not.toBeNull();
    });

    test('stay in the row when their named container does not exist', () => {
        render(wrapper('nowhere', '<p id="lost">Lost</p>'));

        expect(addRow().querySelector('#lost')).not.toBeNull();
    });

    test('are removed with their row, also a portal inside a portal', () => {
        render(
            wrapper(
                'default',
                `<p id="outer">Outer</p>${wrapper('sidebar', '<p id="inner">Inner</p>')}`
            )
        );
        const row = addRow();
        expect(document.querySelector('#outer')).not.toBeNull();
        expect(document.querySelector('#inner')).not.toBeNull();

        destroyComponentsWithin(row);
        row.remove();

        expect(document.querySelector('#outer')).toBeNull();
        expect(document.querySelector('#inner')).toBeNull();
        expect(document.querySelector('template[data-portal-container="sidebar"]')).not.toBeNull();
    });

    test('leave the portals of another row alone when one row is removed', () => {
        render(wrapper('default', '<p class="popup">Popup</p>'));
        const first = addRow();
        addRow();

        destroyComponentsWithin(first);
        first.remove();

        expect(document.querySelectorAll('.popup')).toHaveLength(1);
    });
});
