import { afterEach, expect, test, vi } from 'vitest';
import { Accordion } from '../../../Resources/Private/Primitives/Accordion/Accordion';

let accordion: Accordion | undefined;

afterEach(() => {
    accordion?.destroy();
    accordion = undefined;
    document.body.innerHTML = '';
});

function mountAccordion() {
    document.body.innerHTML = `
        <div data-accordion-root="a1">
            <div data-accordion-item="a1" data-value="one">
                <h3 data-accordion-item-header="a1" data-value="one">
                    <button type="button" data-accordion-item-trigger="a1" data-value="one">One</button>
                </h3>
                <div role="region" hidden data-accordion-item-content="a1" data-value="one" data-state="closed">Content</div>
            </div>
        </div>`;
    accordion = new Accordion({ id: 'a1', collapsible: true }).init();
    return {
        accordion,
        trigger: document.querySelector<HTMLElement>('[data-accordion-item-trigger]')!,
        content: document.querySelector<HTMLElement>('[data-accordion-item-content]')!,
    };
}

test('runs each item content on a collapsible that opens and closes with the item', async () => {
    const { trigger, content } = mountAccordion();

    expect(content.hasAttribute('data-collapsible-content')).toBe(true);
    expect(content.id).toBe(trigger.getAttribute('aria-controls'));
    expect(content.hidden).toBe(true);

    // the accordion only takes a click on a focused trigger
    trigger.focus();
    trigger.click();
    await vi.waitFor(() => expect(content.hidden).toBe(false));
    expect(content.style.getPropertyValue('--height')).not.toBe('');

    // jsdom runs no animation, so the closing state ends right away
    trigger.click();
    await vi.waitFor(() => expect(content.hidden).toBe(true));
    expect(content.dataset.state).toBe('closed');
});

test('keeps a runtime hiddenUntilFound when the collapsible hides the content again', async () => {
    const { accordion, trigger, content } = mountAccordion();
    accordion.updateProps({ hiddenUntilFound: true });

    trigger.focus();
    trigger.click();
    await vi.waitFor(() => expect(content.hidden).toBe(false));
    // the collapsible writes `hidden` when it finishes closing, after the accordion synced it
    trigger.click();
    await vi.waitFor(() => expect(content.hasAttribute('hidden')).toBe(true));
    expect(content.getAttribute('hidden')).toBe('until-found');
});
