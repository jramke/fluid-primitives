<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Service\ContextService;
use TYPO3\CMS\Extbase\Reflection\ObjectAccess;

class FieldContext extends AbstractComponentContext
{
    public function beforeRendering(): void
    {
        $parentRenderingContext = $this->getParentRenderingContext();
        if (!$parentRenderingContext) return;

        $variableContainer = $parentRenderingContext->getViewHelperVariableContainer();
        $isInsideForm = $variableContainer->exists(FormContext::class, 'fieldNamePrefix');
        if ($isInsideForm) {
            $variableContainer->add(self::class, $this->get('rootId'), ['name' => $this->get('name')]);
        }

        $formContext = ContextService::getFromRenderingContext($parentRenderingContext, 'form');
        if ($formContext) {
            $formObject = $formContext->get('object');
            if ($formObject && $this->has('name')) {
                $this->set('defaultValue', ObjectAccess::getPropertyPath($formObject, (string)$this->get('name')));
            }
        }
    }

    public function getChildVariables(): array
    {
        return [
            'name' => $this->get('name') ?? null,
            'disabled' => $this->get('disabled') ?? null,
            'readOnly' => $this->get('readOnly') ?? null,
            'required' => $this->get('required') ?? null,
            'invalid' => $this->get('invalid') ?? null,
            'defaultValue' => $this->get('defaultValue') ?? null,
        ];
    }
}
