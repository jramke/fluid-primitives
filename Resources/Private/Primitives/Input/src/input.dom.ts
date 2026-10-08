import type { Scope } from '@zag-js/core';
import { parts } from './input.anatomy';

// Only the parts other elements point at (`for`, `aria-describedby`) have ids.
export const getInputId = (scope: Scope) => scope.ids?.input ?? `input:${scope.id}:input`;
export const getWordCountId = (scope: Scope) =>
    scope.ids?.wordCount ?? `input:${scope.id}:wordCount`;

export const getInputEl = (scope: Scope) =>
    scope.query<HTMLInputElement>(scope.selector(parts.input));
