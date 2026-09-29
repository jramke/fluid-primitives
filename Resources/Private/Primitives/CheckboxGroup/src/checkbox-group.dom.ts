import type { Scope } from '@zag-js/core';

export const getLabelId = (scope: Scope) => scope.ids?.label ?? `checkbox-group:${scope.id}:label`;
