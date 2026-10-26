import * as select from '@zag-js/select';
import { afterEach, expect, test } from 'vitest';
import { Select } from '../../../Resources/Private/Primitives/Select/Select';

let instance: Select | undefined;

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
});

test('shows the selected label and the placeholder as text, never as markup', () => {
    const label = '<img src=x onerror="window.__pwned=1"><b>Team</b>';
    document.body.innerHTML = `
        <div data-select-root="s1">
            <span data-select-value-text="s1" data-placeholder="R&amp;D &lt;i&gt;none&lt;/i&gt;"></span>
        </div>`;
    const valueText = document.querySelector<HTMLElement>('[data-select-value-text]')!;
    const collection = select.collection({ items: [{ label, value: 'team' }] });

    instance = new Select({ id: 's1', collection, defaultValue: ['team'] });
    instance.init();

    expect(valueText.textContent).toBe(label);
    expect(valueText.querySelector('img, b')).toBeNull();

    instance.api.clearValue();
    return expect
        .poll(() => valueText.textContent)
        .toBe('R&D <i>none</i>')
        .then(() => {
            expect(valueText.querySelector('i')).toBeNull();
        });
});
