import { createScope } from '@zag-js/core';
import { beforeEach, describe, expect, test } from 'vitest';
import {
    focusField,
    getFieldMessages,
} from '../../../Resources/Private/Primitives/Form/src/form.fields';
import type {
    FormErrors,
    FormValidation,
} from '../../../Resources/Private/Primitives/Form/src/form.types';

function form(options: { validation?: FormValidation; serverErrors?: FormErrors } = {}) {
    return {
        scope: createScope({ id: 'f1', getRootNode: () => document }),
        prop: (key: string) => (key === 'validation' ? options.validation : undefined),
        refs: { get: () => options.serverErrors ?? {} },
    } as unknown as Parameters<typeof getFieldMessages>[0];
}

beforeEach(() => {
    document.body.innerHTML = `
        <form data-form-root="f1">
            <input name="email" value="a@b.c">
            <input name="people[0][firstName]" value="">
        </form>
    `;
});

describe('getFieldMessages', () => {
    test('a server error counts only while the field still has the value it was reported for', () => {
        const serverErrors = { email: { messages: ['taken'], value: 'a@b.c' } };
        expect(getFieldMessages(form({ serverErrors }), 'email')).toEqual(['taken']);

        document.querySelector<HTMLInputElement>('input[name="email"]')!.value = 'other@b.c';
        expect(getFieldMessages(form({ serverErrors }), 'email')).toEqual([]);
    });

    test('a server error wins over validation; validation is asked for the one field', () => {
        const validation: FormValidation = ({ values, fieldName }) => {
            const errors: FormErrors = {};
            if (fieldName === 'email' && values.get('email') === 'a@b.c') {
                errors.email = { messages: ['from callback'] };
            }
            return errors;
        };
        const serverErrors = { email: { messages: ['taken'], value: 'a@b.c' } };

        expect(getFieldMessages(form({ validation }), 'email')).toEqual(['from callback']);
        expect(getFieldMessages(form({ validation, serverErrors }), 'email')).toEqual(['taken']);
        expect(getFieldMessages(form({ validation }), 'people[0][firstName]')).toEqual([]);
    });

    test('schema issues map onto bracketed field names, also inside arrays', () => {
        const validation: FormValidation = {
            '~standard': {
                validate: () => ({
                    issues: [
                        { message: 'bad email', path: ['email'] },
                        { message: 'required', path: ['people', 0, 'firstName'] },
                    ],
                }),
            },
        };

        expect(getFieldMessages(form({ validation }), 'email')).toEqual(['bad email']);
        expect(getFieldMessages(form({ validation }), 'people[0][firstName]')).toEqual([
            'required',
        ]);
        expect(getFieldMessages(form(), 'email')).toEqual([]);
    });
});

describe('focusField', () => {
    function mountField(html: string) {
        document.body.innerHTML = `<form><div data-field-root="f1">${html}</div></form>`;
        const rootEl = document.querySelector<HTMLElement>('[data-field-root]')!;
        const fieldMachine = { refs: { get: () => rootEl } } as unknown as Parameters<
            typeof focusField
        >[2];
        return { form: document.querySelector('form')!, fieldMachine };
    }

    test('focuses the control with the name, or wherever that control hands the focus on to', () => {
        const { form, fieldMachine } = mountField(
            '<input name="email" value="a@b.c"><input name="fruit" aria-hidden="true" tabindex="-1"><input id="visible">'
        );

        focusField(form, 'email', fieldMachine);
        const email = document.querySelector<HTMLInputElement>('[name="email"]')!;
        expect(document.activeElement).toBe(email);
        expect([email.selectionStart, email.selectionEnd]).toEqual([0, 5]);

        // like the hidden input of a Combobox: it redirects to the visible control
        document
            .querySelector('[name="fruit"]')!
            .addEventListener('focus', () => document.getElementById('visible')!.focus());
        focusField(form, 'fruit', fieldMachine);
        expect(document.activeElement).toBe(document.getElementById('visible'));
    });

    test('falls back to the first thing in the field that takes focus when the control cannot', () => {
        const { form, fieldMachine } = mountField(
            '<input type="hidden" name="volume"><div aria-hidden="true"></div><div id="thumb" tabindex="0"></div>'
        );

        focusField(form, 'volume', fieldMachine);

        expect(document.activeElement).toBe(document.getElementById('thumb'));
    });
});
