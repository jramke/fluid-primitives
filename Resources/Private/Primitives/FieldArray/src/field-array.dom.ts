import type { Scope } from '@zag-js/core';

export const getLiveRegionId = (scope: Scope) =>
    scope.ids?.liveRegion ?? `field-array:${scope.id}:liveRegion`;
export const getLiveRegionEl = (scope: Scope) => scope.getById(getLiveRegionId(scope));

/**
 * `addTrigger` is a singleton part, server-stamped once and never restamped afterward (unlike
 * `removeTrigger` - see `field-array.connect.ts`'s own docblock on why that one's `id` is left for
 * `ComponentHydrator.restampValue` to own instead), so recomputing it here on every render is safe
 * and matches every other primitive's `getXProps()`.
 */
export const getAddTriggerId = (scope: Scope) =>
    scope.ids?.addTrigger ?? `field-array:${scope.id}:addTrigger`;
