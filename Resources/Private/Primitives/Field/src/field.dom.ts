import type { Scope } from '@zag-js/core';
import { parts } from './field.anatomy';
import type { ValidityMatch } from './field.types';

export type FieldControlElement = HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement;

export const getRootId = (ctx: Scope) => ctx.ids?.root ?? ctx.id;
export const getControlId = (ctx: Scope) => ctx.ids?.control ?? `field:${ctx.id}:control`;
export const getLabelId = (ctx: Scope) => ctx.ids?.label ?? `field:${ctx.id}:label`;
export const getErrorTextId = (ctx: Scope, match?: ValidityMatch | boolean, id?: string) => {
    if (id) return id;
    if (typeof match === 'string') return `field:${ctx.id}:error-text:${match}`;
    return ctx.ids?.errorText ?? `field:${ctx.id}:error-text`;
};
export const getHelperTextId = (ctx: Scope) => ctx.ids?.helperText ?? `field:${ctx.id}:helper-text`;

// Looked up once when the machine starts and kept: a FieldArray row rename re-stamps the root
// marker's value but the scope id is fixed at construction, so a later `scope.selector()` finds nothing.
export const queryRootEl = (ctx: Scope) => ctx.query(ctx.selector(parts.root));
export const getControlEl = (ctx: Scope) => ctx.getById<FieldControlElement>(getControlId(ctx));

export const hasHelperText = (rootEl: HTMLElement) =>
    rootEl.querySelector(`[${parts.helperText.attr}]`) !== null;

export const getVisibleErrorTextIds = (rootEl: HTMLElement) =>
    Array.from(rootEl.querySelectorAll<HTMLElement>(`[${parts.errorText.attr}]`))
        .filter(el => !el.hidden && el.id)
        .map(el => el.id);
