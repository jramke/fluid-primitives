import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { Slider } from '../../../Resources/Private/Primitives/Slider/Slider';

let instance: Slider | undefined;

beforeEach(() => {
    vi.stubGlobal(
        'ResizeObserver',
        class {
            observe() {}
            unobserve() {}
            disconnect() {}
        }
    );
});

afterEach(() => {
    instance?.destroy();
    instance = undefined;
    document.body.innerHTML = '';
    vi.unstubAllGlobals();
});

function renderRange(name: string) {
    document.body.innerHTML = `
        <form><div data-slider-root="s1"><div data-slider-control="s1">
            <div data-slider-thumb="s1" data-value="0"><input type="text" hidden name="${name}" value="20" data-slider-hidden-input="s1"></div>
            <div data-slider-thumb="s1" data-value="1"><input type="text" hidden name="${name}" value="80" data-slider-hidden-input="s1"></div>
        </div></div></form>`;
    return () => [...new FormData(document.querySelector('form')!).entries()];
}

test('submits a range under the name as given, Zag adds no brackets of its own', () => {
    const entries = renderRange('range[]');

    instance = new Slider({ id: 's1', name: 'range[]', defaultValue: [20, 80] });
    instance.init();

    expect(entries()).toEqual([
        ['range[]', '20'],
        ['range[]', '80'],
    ]);
});
