/**
 * Derived from @zag-js/field of Zag.js (https://github.com/chakra-ui/zag, packages/machines/field,
 * commit 0ce8e3de63b571d37e438bcf28e64759466e56e9), MIT License, Copyright (c) 2021 Chakra UI.
 * Adapted for Fluid Primitives, the git history shows what changed from the original.
 */

import type { Scope } from '@zag-js/core';
import { parts } from './field.anatomy';
import type { ValidityMatch } from './field.types';

/** Dispatched on the field root once its settled value differs from the last one it announced. */
export const FIELD_VALUE_CHANGE_EVENT = 'fluid-primitives:field:valuechange';

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

const componentRootAttribute = /^(data-.+-)root$/;

/**
 * Whether `target` belongs to the field: inside its root, or a portaled part (a popup, say) of a
 * component rendered inside it. Those parts sit outside the root but carry
 * `data-<component>-<part>="<id of that component's root>"`, like the root itself.
 */
export function isInsideField(rootEl: HTMLElement, target: EventTarget | null) {
    if (!(target instanceof Element)) return false;
    if (rootEl.contains(target)) return true;

    const prefixById = new Map<string, string>();
    for (const el of rootEl.querySelectorAll('*')) {
        for (const { name, value } of Array.from(el.attributes)) {
            const match = componentRootAttribute.exec(name);
            if (match && value) prefixById.set(value, match[1]);
        }
    }
    if (prefixById.size === 0) return false;

    for (let el: Element | null = target; el; el = el.parentElement) {
        for (const { name, value } of Array.from(el.attributes)) {
            const prefix = prefixById.get(value);
            if (prefix && name.startsWith(prefix)) return true;
        }
    }
    return false;
}
