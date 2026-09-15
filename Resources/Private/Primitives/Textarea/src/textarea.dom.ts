import type { Scope } from '@zag-js/core';

export const getRootId = (scope: Scope) => scope.ids?.root ?? `textarea:${scope.id}`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `textarea:${scope.id}:label`;
export const getTextareaId = (scope: Scope) =>
    scope.ids?.textarea ?? `textarea:${scope.id}:textarea`;
export const getWordCountId = (scope: Scope) =>
    scope.ids?.wordCount ?? `textarea:${scope.id}:word-count`;
export const getLiveRegionId = (scope: Scope) =>
    scope.ids?.liveRegion ?? `textarea:${scope.id}:live-region`;

export const getTextareaEl = (scope: Scope) =>
    scope.getById<HTMLTextAreaElement>(getTextareaId(scope));
