import type { Scope } from '@zag-js/core';
import { parts } from './textarea.anatomy';

// Only the parts other elements point at (`for`, `aria-describedby`) have ids.
export const getTextareaId = (scope: Scope) =>
    scope.ids?.textarea ?? `textarea:${scope.id}:textarea`;
export const getWordCountId = (scope: Scope) =>
    scope.ids?.wordCount ?? `textarea:${scope.id}:wordCount`;

export const getTextareaEl = (scope: Scope) =>
    scope.query<HTMLTextAreaElement>(scope.selector(parts.textarea));
export const getLiveRegionEl = (scope: Scope) => scope.query(scope.selector(parts.liveRegion));
