import type { Scope } from '@zag-js/core';
import { parts } from './field-array.anatomy';

export const getLiveRegionEl = (scope: Scope) => scope.query(scope.selector(parts.liveRegion));
