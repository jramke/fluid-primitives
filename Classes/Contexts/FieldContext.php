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
                $this->set('defaultValue', ObjectAccess::getPropertyPath($formObject, (string)$this->get('name')));
            }
        }
    }

    public function getChildVariables(): array
    {
        $rootId = $this->get('rootId');

        return [
            'name' => $this->get('name') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'readOnly' => $this->get('readOnly') ?? null,
            'required' => $this->get('required') ?? null,
            'invalid' => $this->get('invalid') ?? null,
            'defaultValue' => $this->get('defaultValue') ?? null,
            // Mirrors field.dom.ts's getControlId()/getLabelId() formula, so a field-aware
            // primitive's server-rendered label/control part gets the exact id its client-side
            // propsWithField() override already expects the machine to use. Without this, the
            // two disagree and zag's own internal DOM lookups for that part silently no-op.
            'fieldIds' => $rootId ? [
                'control' => ComponentUtility::generatePartId('field', (string)$rootId, 'control'),
                'label' => ComponentUtility::generatePartId('field', (string)$rootId, 'label'),
            ] : null,
        ];
    }
}
