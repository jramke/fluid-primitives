import type { Scope } from '@zag-js/core';
import { parts } from './field-array.anatomy';

export const getRootEl = (scope: Scope) => scope.query<HTMLElement>(scope.selector(parts.root));
