import type { Scope } from '@zag-js/core';

export const getRootId = (scope: Scope) => scope.ids?.root ?? `input:${scope.id}`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `input:${scope.id}:label`;
export const getInputId = (scope: Scope) => scope.ids?.input ?? `input:${scope.id}:input`;
export const getWordCountId = (scope: Scope) =>
    scope.ids?.wordCount ?? `input:${scope.id}:word-count`;
export const getLiveRegionId = (scope: Scope) =>
    scope.ids?.liveRegion ?? `input:${scope.id}:live-region`;

export const getInputEl = (scope: Scope) => scope.getById<HTMLInputElement>(getInputId(scope));
