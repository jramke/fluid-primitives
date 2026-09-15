import type { Service } from '@zag-js/core';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { parts } from './textarea.anatomy';
import * as dom from './textarea.dom';
import type { TextareaApi, TextareaHandle, TextareaSchema } from './textarea.types';

type TextareaServiceLike = Pick<Service<TextareaSchema>, 'prop' | 'context' | 'scope'>;

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

export function createTextareaHandle(service: TextareaServiceLike): TextareaHandle {
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
    service: Service<TextareaSchema>,
    normalize: NormalizeProps<T>
): TextareaApi {
    const { scope, prop, send } = service;
    const handle = createTextareaHandle(service);

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

        getTextareaProps() {
            return normalize.textarea({
                ...parts.textarea.attrs,
                id: dom.getTextareaId(scope),
                name: handle.name,
                disabled: handle.disabled || undefined,
                readOnly: handle.readOnly || undefined,
                required: handle.required || undefined,
                maxLength: handle.maxLength,
                rows: prop('rows'),
                value: handle.value,
                'aria-invalid': handle.invalid ? 'true' : undefined,
                'data-invalid': handle.invalid ? '' : undefined,
                'data-disabled': handle.disabled ? '' : undefined,
                'data-readonly': handle.readOnly ? '' : undefined,
                onInput(event) {
                    const target = event.currentTarget as HTMLTextAreaElement;
                    const transform = prop('transform');
                    let value = target.value;

                    if (transform) {
                        const transformed = transform(value, event as unknown as Event);
                        if (transformed !== value) {
                            value = transformed;
                            const { selectionStart, selectionEnd } = target;
                            target.value = value;
                            if (selectionStart != null) target.selectionStart = selectionStart;
                            if (selectionEnd != null) target.selectionEnd = selectionEnd;
                        }
                    }

                    send({ type: 'VALUE_CHANGE', value });
                },
                onKeyDown(event) {
                    const submitOn = prop('submitOn');
                    // Ignore Enter while an IME composition is still being finalized (e.g. typing
                    // Japanese/Chinese) - that Enter confirms the composition, it isn't a submit intent.
                    if (!submitOn || event.key !== 'Enter' || event.isComposing) return;

                    const modPressed = event.metaKey || event.ctrlKey;

                    const shouldSubmit =
                        (submitOn === 'enter' && !event.shiftKey && !modPressed) ||
                        (submitOn === 'mod+enter' && modPressed);

                    if (!shouldSubmit) return;

                    event.preventDefault();
                    (event.currentTarget as HTMLTextAreaElement).closest('form')?.requestSubmit();
                },
            });
        },

        getLabelProps() {
            return normalize.label({
                ...parts.label.attrs,
                id: dom.getLabelId(scope),
                htmlFor: dom.getTextareaId(scope),
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
