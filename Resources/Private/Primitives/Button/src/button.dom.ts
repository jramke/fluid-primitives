import type { Scope } from '@zag-js/core';
import { parts } from './button.anatomy';

export const getRootEl = (scope: Scope) =>
    scope.query<HTMLButtonElement>(scope.selector(parts.root));
