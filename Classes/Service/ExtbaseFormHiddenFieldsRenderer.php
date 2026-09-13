<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Jramke\FluidPrimitives\Domain\Dto\ResolvedFormPersistedObjects;
use Jramke\FluidPrimitives\Utility\ExtbaseFormFieldNamer;
use TYPO3\CMS\Extbase\DomainObject\AbstractDomainObject;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;

/**
 * Renders the hidden `__identity` and `__trustedProperties` fields Extbase's `PersistentObjectConverter`
 * and property mapper require for a form submission to correctly round-trip - the counterpart of what
 * `<f:form>` gets for free from core's `AbstractFormViewHelper`, which `<ui:form.root>` - not built on
 * `<f:form>` - otherwise has no equivalent for.
 */
final readonly class ExtbaseFormHiddenFieldsRenderer
{
    public function __construct(
        private MvcPropertyMappingConfigurationService $mvcPropertyMappingConfigurationService,
        private ExtbaseFormFieldNamer $fieldNamer = new ExtbaseFormFieldNamer(),
    ) {}

    /**
     * Renders a hidden field carrying the technical identity (uid) of `$objects->boundObject` (if
     * bound), plus one for each of `$objects->nestedObjects` (keyed by their dot-notation property
     * path) - without its own `__identity`, submitting the form would make Extbase create a new
     * object rather than update the existing one, silently orphaning persisted sub-objects.
     */
    public function renderIdentityFields(
        ResolvedFormPersistedObjects $objects,
        ?string $objectName,
        string $fieldNamePrefix,
        bool $xhtmlCompliant,
    ): string {
        $html = $this->renderHiddenIdentityField(
            $objects->boundObject,
            null,
            $objectName,
            $fieldNamePrefix,
            $xhtmlCompliant,
        );

        foreach ($objects->nestedObjects as $propertyPath => $nestedObject) {
            $html .= $this->renderHiddenIdentityField(
                $nestedObject,
                $propertyPath,
                $objectName,
                $fieldNamePrefix,
                $xhtmlCompliant,
            );
        }

        return $html;
    }

    /**
     * Renders the `__trustedProperties` field: an HMAC-signed token (via
     * `MvcPropertyMappingConfigurationService`) over every field name this form is allowed to map back
     * onto its bound object(s), including the `__identity` fields above - whether modification (vs.
     * creation) of a persistent object is permitted is derived entirely from whether `__identity` is
     * present in this signed token, not from the raw `__identity` field alone.
     *
     * @param array<string, array{name?: string}> $fieldContextInformations
     */
    public function renderTrustedPropertiesField(
        array $fieldContextInformations,
        ResolvedFormPersistedObjects $objects,
        ?string $objectName,
        string $fieldNamePrefix,
        bool $xhtmlCompliant,
    ): string {
        $fieldNames = [];

        foreach ($fieldContextInformations as $fieldContextData) {
            if (!isset($fieldContextData['name'])) {
                continue;
            }

            $fieldNames[] = $this->fieldNamer->prefixFieldName(
                $fieldContextData['name'],
                $objectName,
                $fieldNamePrefix,
            );
        }

        if ($objects->boundObject instanceof AbstractDomainObject) {
            $fieldNames[] = $this->fieldNamer->prefixFieldName('__identity', $objectName, $fieldNamePrefix);
        }

        foreach (array_keys($objects->nestedObjects) as $propertyPath) {
            $fieldNames[] = $this->fieldNamer->prefixFieldName(
                $propertyPath . '.__identity',
                $objectName,
                $fieldNamePrefix,
            );
        }

        $requestHash = $this->mvcPropertyMappingConfigurationService->generateTrustedPropertiesToken(
            $fieldNames,
            $fieldNamePrefix,
        );

        return $this->hiddenInput(
            $this->fieldNamer->prefixFieldName('__trustedProperties', null, $fieldNamePrefix),
            $requestHash,
            $xhtmlCompliant,
        );
    }

    private function renderHiddenIdentityField(
        ?AbstractDomainObject $object,
        ?string $propertyPath,
        ?string $objectName,
        string $fieldNamePrefix,
        bool $xhtmlCompliant,
    ): string {
        if (!$object instanceof AbstractDomainObject) {
            return '';
        }

        $fieldName = $propertyPath === null ? '__identity' : $propertyPath . '.__identity';
        $name = $this->fieldNamer->prefixFieldName($fieldName, $objectName, $fieldNamePrefix);

        return $this->hiddenInput($name, (string)$object->getUid(), $xhtmlCompliant);
    }

    private function hiddenInput(string $name, string $value, bool $xhtmlCompliant): string
    {
        return (
            '<input type="hidden" name="' .
            htmlspecialchars($name) .
            '" value="' .
            htmlspecialchars($value) .
            '" ' .
            ($xhtmlCompliant ? '/' : '') .
            '>'
        );
    }
}
