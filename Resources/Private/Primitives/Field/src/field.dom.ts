import type { Scope } from '@zag-js/core';

export const getRootId = (scope: Scope) => scope.ids?.root ?? `field:${scope.id}:root`;
export const getLabelId = (scope: Scope) => scope.ids?.label ?? `field:${scope.id}:label`;
export const getControlId = (scope: Scope) => scope.ids?.control ?? `field:${scope.id}:control`;
export const getErrorId = (scope: Scope) => scope.ids?.error ?? `field:${scope.id}:error`;
export const getDescriptionId = (scope: Scope) =>
	scope.ids?.description ?? `field:${scope.id}:description`;
export const getRootEl = (scope: Scope) => scope.getById(getRootId(scope));
export const getLabelEl = (scope: Scope) => scope.getById(getLabelId(scope));
export const getControlEl = (scope: Scope) => scope.getById(getControlId(scope));
export const getErrorEl = (scope: Scope) => scope.getById(getErrorId(scope));
export const getDescriptionEl = (scope: Scope) => scope.getById(getDescriptionId(scope));

export const getClosestFieldRoot = (target: Element | null) => {
	return target?.closest('[data-scope="field"][data-part="root"]') ?? null;
};

export const getClosestFieldName = (target: Element | null): string | undefined => {
	if (!target) return;

	if ('name' in target && typeof target.name === 'string' && target.name) {
		return target.name;
	}

	return getClosestFieldRoot(target)?.getAttribute('data-name') || undefined;
};

export const isFocusMovingWithinSameField = (
	target: Element | null,
	relatedTarget: EventTarget | null
) => {
	if (!(relatedTarget instanceof Element)) return false;

	const currentField = getClosestFieldRoot(target);
	const nextField = getClosestFieldRoot(relatedTarget);

	return !!currentField && currentField === nextField;
};
