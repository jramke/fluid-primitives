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
    public function __construct(
        private ExtbaseFormFieldNamer $fieldNamer,
    ) {}

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
     * The given value (unwrapped if it's a lazy proxy), if it's a domain object with a real uid.
     * Deliberately mirrors the checks of core's `AbstractFormViewHelper::renderHiddenIdentityField()`:
     * https://github.com/TYPO3/typo3/blob/07f4dc8267d1824a3021c00cd36c5ad8ee7f84a1/typo3/sysext/fluid/Classes/ViewHelpers/Form/AbstractFormViewHelper.php#L77
     */
    // This method is already a minimal, guard-clause chain of type-narrowing checks (proxy unwrap,
    // instanceof, uid presence) - the operator variety relative to its short length
    // is what trips the halstead difficulty threshold, not any real complexity a split would reduce.
    // @mago-expect lint:halstead
    private function resolve(mixed $object): ?AbstractDomainObject
    {
        if ($object instanceof LazyLoadingProxy) {
            $object = $object->_loadRealInstance();
        }

        if (!$object instanceof AbstractDomainObject) {
            return null;
        }

        return $object->getUid() !== null ? $object : null;
    }

    /**
     * Persisted domain sub-objects directly nested under `$rootObject` (e.g. `person` on an
     * `EventRegistration`), keyed by their property name. Derived from the names of the
     * `<ui:field.root>`s actually used in the form, in dot or bracket notation (`person.name` and
     * `person[name]` both yield `person`) -
     * fields on the root object itself (e.g. `ticketType`) are skipped, since those aren't sub-objects
     * and are already covered by `resolve($rootObject)` itself.
     *
     * Only one level deep: this doesn't recurse into further-nested sub-objects, which the form
     * primitives have no support for as field paths today (e.g. `person.address.city`) anyway.
     *
     * @param array<string, array{name?: string}> $fieldContextInformations
     * @return array<string, AbstractDomainObject>
     */
    private function getNestedPersistedObjects(
        ?AbstractDomainObject $rootObject,
        array $fieldContextInformations,
    ): array {
        if (!$rootObject instanceof AbstractDomainObject) {
            return [];
        }

        $nestedObjects = [];
        foreach ($fieldContextInformations as $fieldContextData) {
            $path = $this->fieldNamer->parseFieldPath($fieldContextData['name'] ?? '');
            if (count($path) < 2) {
                continue;
            }

            $propertyPath = $path[0];
            if (($nestedObjects[$propertyPath] ?? null) instanceof AbstractDomainObject) {
                continue;
            }

            // the lenient accessor: the first segment of a field of a FieldArray row (`people[0][name]`) is
            // not necessarily a property of the object
            $nestedObject = $this->resolve(ObjectAccess::getPropertyPath($rootObject, $propertyPath));
            if ($nestedObject instanceof AbstractDomainObject) {
                $nestedObjects[$propertyPath] = $nestedObject;
            }
        }

        return $nestedObjects;
    }
}
