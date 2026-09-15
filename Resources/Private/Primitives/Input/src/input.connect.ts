import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './input.anatomy';
import * as dom from './input.dom';
import type { InputApi, InputHandle, InputSchema } from './input.types';

type InputServiceLike = Pick<Service<InputSchema>, 'prop' | 'context' | 'scope'>;

function formatCountText(
    count: number,
    maxLength: number | undefined,
    wordCountTemplate: string | false | undefined
): string | null {
    if (!wordCountTemplate || maxLength == null) return null;
    return wordCountTemplate
        .replaceAll('%count%', String(count))
        .replaceAll('%max%', String(maxLength));
}

// Not every <input> type supports selection - `type="email"`/`"number"` etc. throw a
// DOMException on `.selectionStart`/`.selectionEnd` access per the HTML spec.
const SELECTABLE_INPUT_TYPES = new Set(['text', 'search', 'tel', 'url', 'password']);

export function createInputHandle(service: InputServiceLike): InputHandle {
    const { prop, context } = service;

    const value = context.get('value');
    const maxLength = prop('maxLength');
    const count = value.length;
    const translations = prop('translations');

    return {
        value,
        count,
        countText: formatCountText(count, maxLength, translations?.wordCount),
        disabled: !!prop('disabled'),
        readOnly: !!prop('readOnly'),
        required: !!prop('required'),
        invalid: !!prop('invalid'),
        name: prop('name'),
        maxLength,
    };
}

export function connect<T extends PropTypes>(
    service: Service<InputSchema>,
    normalize: NormalizeProps<T>
): InputApi {
    const { scope, prop, send } = service;
    const handle = createInputHandle(service);

    return {
        ...handle,

        getRootProps() {
            return normalize.element({
                ...parts.root.attrs,
                id: dom.getRootId(scope),
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
            });
        },

        getInputProps() {
            return normalize.input({
                ...parts.input.attrs,
                id: dom.getInputId(scope),
                name: handle.name,
                disabled: handle.disabled || undefined,
                readOnly: handle.readOnly || undefined,
                required: handle.required || undefined,
                maxLength: handle.maxLength,
                value: handle.value,
                'aria-invalid': handle.invalid ? 'true' : undefined,
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
                onInput(event) {
                    const target = event.currentTarget as HTMLInputElement;
                    const transform = prop('transform');
                    let value = target.value;

                    if (transform) {
                        const transformed = transform(value, event as unknown as Event);
                        if (transformed !== value) {
                            value = transformed;
                            if (SELECTABLE_INPUT_TYPES.has(target.type)) {
                                const { selectionStart, selectionEnd } = target;
                                target.value = value;
                                if (selectionStart != null) target.selectionStart = selectionStart;
                                if (selectionEnd != null) target.selectionEnd = selectionEnd;
                            } else {
                                target.value = value;
                            }
                        }
                    }

                    send({ type: 'VALUE_CHANGE', value });
                },
            });
        },

        getLabelProps() {
            return normalize.label({
                ...parts.label.attrs,
                id: dom.getLabelId(scope),
                htmlFor: dom.getInputId(scope),
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-required': handle.required ? '' : undefined,
            });
        },

        // Visually shows the same count a screen reader hears from the live region - hidden from
        // AT so its text-content mutations on every keystroke aren't redundantly picked up by
        // whichever assistive tech already monitors generic DOM changes.
        getWordCountProps() {
            return normalize.element({
                ...parts.wordCount.attrs,
                id: dom.getWordCountId(scope),
                'aria-hidden': 'true',
            });
        },

        getLiveRegionProps() {
            return normalize.element({
                ...parts.liveRegion.attrs,
                id: dom.getLiveRegionId(scope),
            });
        },
    };
}
