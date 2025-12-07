import type { Scope } from '@zag-js/core';

export const getFormId = (scope: Scope) => scope.ids?.form ?? `form:${scope.id}:form`;

export const getFormEl = (scope: Scope) => scope.getById(getFormId(scope)) as HTMLFormElement;
