import type { Scope } from '@zag-js/core';
import { parts } from './checkbox-group.anatomy';

export const getLabelId = (scope: Scope) => scope.ids?.label ?? `checkbox-group:${scope.id}:label`;

/** The group's own label part, or a surrounding Field's label carrying the mapped label id. */
export const getLabelEl = (scope: Scope) =>
    scope.query(scope.selector(parts.label)) ?? scope.getById(getLabelId(scope));
