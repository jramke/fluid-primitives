<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Persistence\Generic\LazyLoadingProxy;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

/**
 * Normalizes raw values (a Form's `object` argument, or properties of it) into the persisted domain
 * objects they represent - the condition under which Extbase needs a `__identity` field to recognize
 * a form submission as a modification rather than a creation.
 */
final readonly class ExtbasePersistedObjectResolver
{
    /**
     * Resolves a form's raw `object` argument and its nested sub-objects (per
     * {@see getNestedPersistedObjects()}) together.
     *
     * @param array<string, array{name?: string}> $fieldContextInformations
     */
    public function resolveForForm(mixed $rawBoundObject, array $fieldContextInformations): ResolvedFormPersistedObjects
    {
        $boundObject = $this->resolve($rawBoundObject);

        return new ResolvedFormPersistedObjects(
            $boundObject,
            $this->getNestedPersistedObjects($boundObject, $fieldContextInformations),
        );
    }

    /**
     * The given value, if it's a persisted domain object with a real uid - either not new, or a
     * clone of a previously-persisted one (e.g. the same instance re-rendered after a validation
     * error).
     */
    public function resolve(mixed $object): ?AbstractDomainObject
    {
        if ($object instanceof LazyLoadingProxy) {
            $object = $object->_loadRealInstance();
        }

        if (!$object instanceof AbstractDomainObject) {
            return null;
        }

        if ($object->_isNew() && !$object->_isClone()) {
            return null;
        }

        return $object->getUid() !== null ? $object : null;
    }

    /**
     * Persisted domain sub-objects directly nested under `$rootObject` (e.g. `person` on an
     * `EventRegistration`), keyed by their dot-notation property path. Derived from the property
     * paths of `<ui:field.root>`s actually used in the form (e.g. `person.name` yields `person`) -
     * fields on the root object itself (e.g. `ticketType`) are skipped, since those aren't sub-objects
     * and are already covered by `resolve($rootObject)` itself.
     *
     * Only one level deep: this doesn't recurse into further-nested sub-objects, which the form
     * primitives have no support for as field paths today (e.g. `person.address.city`) anyway.
     *
     * @param array<string, array{name?: string}> $fieldContextInformations
     * @return array<string, AbstractDomainObject>
     */
    public function getNestedPersistedObjects(?AbstractDomainObject $rootObject, array $fieldContextInformations): array
    {
        if ($rootObject === null) {
            return [];
        }

        $nestedObjects = [];
        foreach ($fieldContextInformations as $fieldContextData) {
            $name = (string)($fieldContextData['name'] ?? '');
            if (!str_contains($name, '.')) {
                continue;
            }

            $propertyPath = substr($name, 0, strpos($name, '.'));
            if (isset($nestedObjects[$propertyPath])) {
                continue;
            }

            $nestedObject = $this->resolve(ObjectAccess::getProperty($rootObject, $propertyPath));
            if ($nestedObject !== null) {
                $nestedObjects[$propertyPath] = $nestedObject;
            }
        }

        return $nestedObjects;
    }
}
