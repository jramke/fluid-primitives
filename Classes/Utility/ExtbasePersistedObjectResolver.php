<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Domain\Dto\ResolvedFormPersistedObjects;
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

        return new ResolvedFormPersistedObjects($boundObject, $this->getNestedPersistedObjects(
            $boundObject,
            $fieldContextInformations,
        ));
    }

    /**
     * The given value, if it's a persisted domain object with a real uid - either not new, or a
     * clone of a previously-persisted one (e.g. the same instance re-rendered after a validation
     * error).
     */
    // This method is already a minimal, guard-clause chain of type-narrowing checks (proxy unwrap,
    // instanceof, new/clone state, uid presence) - the operator variety relative to its short length
    // is what trips the halstead difficulty threshold, not any real complexity a split would reduce.
    // @mago-expect lint:halstead
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
        if (!$rootObject instanceof AbstractDomainObject) {
            return [];
        }

        $nestedObjects = [];
        foreach ($fieldContextInformations as $fieldContextData) {
            $name = $fieldContextData['name'] ?? '';
            $dotPosition = strpos($name, needle: '.');
            if ($dotPosition === false) {
                continue;
            }

            $propertyPath = substr($name, offset: 0, length: $dotPosition);
            if (($nestedObjects[$propertyPath] ?? null) instanceof AbstractDomainObject) {
                continue;
            }

            $nestedObject = $this->resolve(ObjectAccess::getProperty($rootObject, $propertyPath));
            if ($nestedObject instanceof AbstractDomainObject) {
                $nestedObjects[$propertyPath] = $nestedObject;
            }
        }

        return $nestedObjects;
    }
}
