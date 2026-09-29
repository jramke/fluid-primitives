import type { Scope } from '@zag-js/core';
import { parts } from './form.anatomy';

export const getFormEl = (scope: Scope) =>
    scope.query(scope.selector(parts.root)) as HTMLFormElement;
