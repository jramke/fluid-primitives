import type { Scope } from '@zag-js/core';

export const getRootId = (scope: Scope) => scope.ids?.root ?? `checkbox-group:${scope.id}:root`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `checkbox-group:${scope.id}:label`;
export const getRootEl = (scope: Scope) => scope.getById(getRootId(scope));
export const getLabelEl = (scope: Scope) => scope.getById(getLabelId(scope));
