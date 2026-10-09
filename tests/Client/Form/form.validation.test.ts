import * as v from 'valibot';
import { describe, expect, test } from 'vitest';
import { z } from 'zod';
import {
    getFormErrorMessages,
    mapServerErrors,
    validateWithValidation,
} from '../../../Resources/Private/Primitives/Form/src/form.validation';
import { createFormValues } from '../../../Resources/Private/Primitives/Form/src/form.values';

function values(entries: Array<[string, string]>) {
    const data = new FormData();
    for (const [name, value] of entries) data.append(name, value);
    return createFormValues(data);
}

const schemas = {
    zod: z.object({
        email: z.string().min(5, 'email too short'),
        people: z.array(z.object({ name: z.string().min(1, 'name required') })),
    }),
    // valibot reports its path segments as objects with a `key`, zod as plain keys
    valibot: v.object({
        email: v.pipe(v.string(), v.minLength(5, 'email too short')),
        people: v.array(v.object({ name: v.pipe(v.string(), v.minLength(1, 'name required')) })),
    }),
};

describe.each(Object.entries(schemas))('validation with a %s schema', (_library, schema) => {
    test('issues become errors by bracketed field name, carrying the value they were found for', () => {
        const invalid = values([
            ['email', 'a@b'],
            ['people[0][name]', ''],
        ]);

        expect(validateWithValidation(schema, invalid)).toEqual({
            email: { messages: ['email too short'], value: 'a@b' },
            'people[0][name]': { messages: ['name required'], value: '' },
        });

        const valid = values([
            ['email', 'ada@example.com'],
            ['people[0][name]', 'Ada'],
        ]);
        expect(validateWithValidation(schema, valid)).toEqual({});
    });
});

describe('validation of a form', () => {
    test('several issues of one field add up, and issues without a usable field path are dropped', () => {
        const schema = {
            '~standard': {
                validate: () => ({
                    issues: [
                        { message: 'for the whole form' },
                        { message: 'for the root', path: [] },
                        { message: 'for a symbol', path: [Symbol('x')] },
                        { message: 'too short', path: ['code'] },
                        { message: 'must start with x', path: [{ key: 'code' }] },
                    ],
                }),
            },
        };

        const errors = validateWithValidation(schema, values([['code', 'ab']]));

        expect(errors).toEqual({
            code: { messages: ['too short', 'must start with x'], value: 'ab' },
        });
    });

    test('an async schema is refused, since the form validates synchronously on every change', () => {
        const asyncSchema = { '~standard': { validate: async () => ({ issues: [] }) } };

        expect(() => validateWithValidation(asyncSchema, values([]))).toThrow(
            'Form validation must be synchronous'
        );
    });
});

describe('server validation errors', () => {
    test('a 422 body keyed by property path maps onto field names, apart from errors of the form itself', () => {
        const submitted = values([
            ['title', 'ab'],
            ['attendees[0][name]', ''],
        ]);

        // the shape of JsonValidationErrorsTrait: property path => messages
        expect(
            mapServerErrors(
                { 'event.title': ['too short'], 'event.attendees.0.name': ['required'] },
                'event',
                submitted
            )
        ).toEqual({
            title: { messages: ['too short'], value: 'ab' },
            'attendees[0][name]': { messages: ['required'], value: '' },
        });

        expect(getFormErrorMessages({ event: ['The event is invalid'] }, 'event')).toEqual([
            'The event is invalid',
        ]);
        expect(getFormErrorMessages({ 'event.title': ['too short'] }, 'event')).toBeNull();
        expect(getFormErrorMessages({ event: ['The event is invalid'] }, undefined)).toBeNull();
    });
});
