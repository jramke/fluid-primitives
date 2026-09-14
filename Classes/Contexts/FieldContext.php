<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentPartIdUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class FieldContext extends AbstractComponentContext
{
    public function beforeRendering(): void
    {
        $parentRenderingContext = $this->getParentRenderingContext();

        $variableContainer = $parentRenderingContext->getViewHelperVariableContainer();
        $variableContainer->add(self::class, Typed::string($this->get('rootId')), ['name' => $this->get('name')]);

        $formContext = ContextService::getFromRenderingContext($parentRenderingContext, 'form');
        if ($formContext instanceof ComponentContextInterface) {
            // Narrowed immediately below via is_object() - no Typed:: equivalent for objects.
            // @mago-expect analysis:mixed-assignment
            $formObject = $formContext->get('object');
            if (is_object($formObject) && $this->has('name')) {
                // A trailing "[]" (e.g. `name="a11yNeeds[]"`, the manual-bracket convention
                // CheckboxGroup and FileUpload's multi-file `name` prop both rely on) is a
                // submission-name detail, not part of the property path - ObjectAccess has no
                // concept of it and throws when it's left in.
                $name = (string)$this->get('name');
                $propertyPath = str_ends_with($name, '[]') ? substr($name, offset: 0, length: -2) : $name;
                $this->set('defaultValue', ObjectAccess::getPropertyPath($formObject, $propertyPath));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getChildVariables(): array
    {
        $rootId = Typed::string($this->get('rootId'));

        $givenIds = (array)($this->get('ids') ?? []);
        $ids = array_merge($givenIds, [
            'control' => ComponentPartIdUtility::generatePartId('field', $rootId, 'control'),
            'label' => ComponentPartIdUtility::generatePartId('field', $rootId, 'label'),
        ]);

        return [
            'name' => Typed::stringOrNull($this->get('name')),
            'disabled' => Typed::boolOrNull($this->get('disabled')),
            'readOnly' => Typed::boolOrNull($this->get('readOnly')),
            'required' => Typed::boolOrNull($this->get('required')),
            'invalid' => Typed::boolOrNull($this->get('invalid')),
            'defaultValue' => $this->get('defaultValue') ?? null,
            'ids' => $ids,
        ];
    }
}
