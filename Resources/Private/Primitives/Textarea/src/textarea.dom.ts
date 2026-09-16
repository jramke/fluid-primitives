import type { Scope } from '@zag-js/core';

export const getRootId = (scope: Scope) => scope.ids?.root ?? `textarea:${scope.id}`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `textarea:${scope.id}:label`;
export const getTextareaId = (scope: Scope) =>
    scope.ids?.textarea ?? `textarea:${scope.id}:textarea`;
// No PART_SEGMENT_OVERRIDES entry exists for 'textarea' server-side (ComponentPartIdUtility), so the
// server generates these ids from the raw (camelCase) part name - matching that exactly here is
// what lets scope.getById() find the element at all on the very first client render.
export const getWordCountId = (scope: Scope) =>
    scope.ids?.wordCount ?? `textarea:${scope.id}:wordCount`;
export const getLiveRegionId = (scope: Scope) =>
    scope.ids?.liveRegion ?? `textarea:${scope.id}:liveRegion`;

export const getTextareaEl = (scope: Scope) =>
    scope.getById<HTMLTextAreaElement>(getTextareaId(scope));
export const getLiveRegionEl = (scope: Scope) => scope.getById(getLiveRegionId(scope));
