import type { Scope } from '@zag-js/core';

export const getRootId = (scope: Scope) => scope.ids?.form ?? `field:${scope.id}:root`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `field:${scope.id}:label`;
export const getControlId = (scope: Scope) => scope.ids?.control ?? `field:${scope.id}:control`;
export const getErrorId = (scope: Scope) => scope.ids?.error ?? `field:${scope.id}:error`;

export const getRootEl = (scope: Scope) => scope.getById(getRootId(scope));
export const getLabelEl = (scope: Scope) => scope.getById(getLabelId(scope));
export const getControlEl = (scope: Scope) => scope.getById(getControlId(scope));
export const getErrorEl = (scope: Scope) => scope.getById(getErrorId(scope));
