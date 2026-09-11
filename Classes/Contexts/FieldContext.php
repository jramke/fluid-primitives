<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class FieldContext extends AbstractComponentContext
{
    public function beforeRendering(): void
    {
        $parentRenderingContext = $this->getParentRenderingContext();
        if (!$parentRenderingContext) {
            return;
        }

        $variableContainer = $parentRenderingContext->getViewHelperVariableContainer();
        $variableContainer->add(self::class, $this->get('rootId'), ['name' => $this->get('name')]);

        $formContext = ContextService::getFromRenderingContext($parentRenderingContext, 'form');
        if ($formContext instanceof ComponentContextInterface) {
            $formObject = $formContext->get('object');
            if ($formObject && $this->has('name')) {
                // A trailing "[]" (e.g. `name="a11yNeeds[]"`, the manual-bracket convention
                // CheckboxGroup and FileUpload's multi-file `name` prop both rely on) is a
                // submission-name detail, not part of the property path - ObjectAccess has no
                // concept of it and throws when it's left in.
                $name = (string)$this->get('name');
                $propertyPath = str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
                $this->set('defaultValue', ObjectAccess::getPropertyPath($formObject, $propertyPath));
            }
        }
    }

    public function getChildVariables(): array
    {
        $rootId = $this->get('rootId');

        $givenIds = (array)($this->get('ids') ?? []);
        $ids = array_merge($givenIds, [
            'control' => ComponentUtility::generatePartId('field', (string)$rootId, 'control'),
            'label' => ComponentUtility::generatePartId('field', (string)$rootId, 'label'),
        ]);

        return [
            'name' => $this->get('name') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'readOnly' => $this->get('readOnly') ?? null,
            'required' => $this->get('required') ?? null,
            'invalid' => $this->get('invalid') ?? null,
            'defaultValue' => $this->get('defaultValue') ?? null,
            'ids' => $ids,
        ];
    }
}
