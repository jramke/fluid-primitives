import type { Scope } from '@zag-js/core';
import { parts } from './field-array.anatomy';

export const getLiveRegionEl = (scope: Scope) => scope.query(scope.selector(parts.liveRegion));

/**
 * `addTrigger` is a singleton part, server-stamped once and never restamped afterward (unlike
 * `removeTrigger` - see `field-array.connect.ts`'s own docblock on why that one's `id` is left for
 * `ComponentHydrator.restampValue` to own instead), so recomputing it here on every render is safe
 * and matches every other primitive's `getXProps()`.
 */
export const getAddTriggerId = (scope: Scope) =>
    scope.ids?.addTrigger ?? `field-array:${scope.id}:addTrigger`;
