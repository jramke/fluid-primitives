import type { Scope } from '@zag-js/core';
import { parts } from './textarea.anatomy';

export const getRootId = (scope: Scope) => scope.ids?.root ?? `textarea:${scope.id}`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `textarea:${scope.id}:label`;
export const getTextareaId = (scope: Scope) =>
    scope.ids?.textarea ?? `textarea:${scope.id}:textarea`;
export const getWordCountId = (scope: Scope) =>
    scope.ids?.wordCount ?? `textarea:${scope.id}:wordCount`;
export const getLiveRegionId = (scope: Scope) =>
    scope.ids?.liveRegion ?? `textarea:${scope.id}:liveRegion`;

export const getTextareaEl = (scope: Scope) =>
    scope.query<HTMLTextAreaElement>(scope.selector(parts.textarea));
export const getLiveRegionEl = (scope: Scope) => scope.query(scope.selector(parts.liveRegion));
