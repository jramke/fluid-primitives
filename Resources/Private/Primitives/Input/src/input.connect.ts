import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './input.anatomy';
import * as dom from './input.dom';
import type { InputApi, InputHandle, InputSchema } from './input.types';

type InputServiceLike = Pick<Service<InputSchema>, 'prop' | 'context' | 'scope' | 'computed'>;

// Not every <input> type supports selection - `type="email"`/`"number"` etc. throw a
// DOMException on `.selectionStart`/`.selectionEnd` access per the HTML spec.
const SELECTABLE_INPUT_TYPES = new Set(['text', 'search', 'tel', 'url', 'password']);

export function createInputHandle(service: InputServiceLike): InputHandle {
    const { prop, context, computed } = service;
    const over = computed('over');
    const maxLength = prop('maxLength');

    return {
        value: context.get('value'),
        count: computed('count'),
        over,
        atLimit: maxLength != null && computed('count') === maxLength,
        countText: computed('countText'),
        disabled: !!prop('disabled'),
        readOnly: !!prop('readOnly'),
        required: !!prop('required'),
        // a field, or the `invalid` prop, decides on its own - only a lone control judges its length
        invalid: prop('invalid') ?? (prop('maxLengthBehavior') === 'invalid' && over > 0),
        name: prop('name'),
        maxLength: prop('maxLength'),
    };
}

export function connect<T extends PropTypes>(
    service: Service<InputSchema>,
    normalize: NormalizeProps<T>
): InputApi {
    const { scope, prop, send } = service;
    const handle = createInputHandle(service);
    // only `block` leaves the limit to the browser, which cuts a paste off at it
    const nativeMaxLength =
        (prop('maxLengthBehavior') ?? 'block') === 'block' ? handle.maxLength : undefined;
    const limitAttrs = {
        /** The `maxLengthBehavior` while there is a `maxLength`: `block`, `warn` or `invalid`. Style the over-limit state of `warn` and `invalid` apart. */
        'data-max-length-behavior':
            handle.maxLength == null ? undefined : (prop('maxLengthBehavior') ?? 'block'),
        /** Present while the value is exactly `maxLength` characters long. */
        'data-at-limit': handle.atLimit ? '' : undefined,
        /** Present while the value is longer than `maxLength` and `maxLengthBehavior` is not `block`. */
        'data-over-limit': handle.over > 0 ? '' : undefined,
    };

    return {
        ...handle,

        getRootProps() {
            return normalize.element({
                ...parts.root.attrs(scope.id),
                ...limitAttrs,
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
            });
        },

        getInputProps() {
            return normalize.input({
                ...parts.input.attrs(scope.id),
                id: dom.getInputId(scope),
                name: handle.name,
                disabled: handle.disabled || undefined,
                readOnly: handle.readOnly || undefined,
                required: handle.required || undefined,
                maxLength: nativeMaxLength,
                pattern: prop('pattern'),
                inputMode: prop('inputMode'),
                value: handle.value,
                'aria-invalid': handle.invalid ? 'true' : undefined,
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
                ...limitAttrs,
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
                ...parts.label.attrs(scope.id),
                htmlFor: dom.getInputId(scope),
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-required': handle.required ? '' : undefined,
            });
        },

        // Visually shows the same count a screen reader hears through the announcements - hidden
        // from AT so its text-content mutations on every keystroke aren't redundantly picked up by
        // whichever assistive tech already monitors generic DOM changes.
        getWordCountProps() {
            return normalize.element({
                ...parts.wordCount.attrs(scope.id),
                id: dom.getWordCountId(scope),
                ...limitAttrs,
                'aria-hidden': 'true',
            });
        },
    };
}
