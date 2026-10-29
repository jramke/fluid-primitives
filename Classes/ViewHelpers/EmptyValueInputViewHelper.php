<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\TagAttributes;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use Jramke\FluidPrimitives\Registry\ReferencedRootRegistry;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentRefUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Renders the input a form control submits while it is empty.
 *
 * A browser leaves an unchecked checkbox or a field without a value out of the form, so Extbase never learns that the property was cleared. This input submits `''` under the name of the control, and the control disables it while it has a value.
 *
 * ```html
 * <ui:emptyValueInput />
 * ```
 *
 * Place it in the template of a component whose context uses `HasEmptyValueInputTrait`, before the native input that holds the value: PHP keeps the last value under a name, so the real value wins. It renders nothing while the context has no `emptyValueName`.
 *
 * A name that ends with `[]` is submitted without the brackets (`tags`, not `tags[]`), which Extbase maps to an empty array or ObjectStorage, where `['']` would hold one empty entry. `data-empty-value` carries the full name, for the client to read an empty list.
 *
 * Call `syncEmptyValueInput()` from the `render()` of the component to disable the input while the control has a value.
 *
 */
class EmptyValueInputViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function render(): string
    {
        $renderingContext = $this->renderingContext ?? throw new \LogicException(
            'EmptyValueInput ViewHelper is missing its rendering context.',
            1_788_100_050,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new MissingContextException(
                'The emptyValueInput ViewHelper can only be used inside a component context.',
                1_788_100_051,
            );
        }

        $variables = $renderingContext->getVariableProvider();
        $name = Typed::stringOrNull($variables->getByPath('context.emptyValueName'));
        if ($name === null) {
            return '';
        }

        $clientBaseName = ComponentNameUtility::getClientBaseNameFromContext($renderingContext);
        $rootId = ComponentUtility::getRootIdFromContext($renderingContext);
        ReferencedRootRegistry::mark($clientBaseName, $rootId);

        $attributes = new TagAttributes([
            'type' => 'hidden',
            'name' => $name,
            'value' => '',
            'data-empty-value' => Typed::string($variables->getByPath('context.name')),
            'form' => Typed::stringOrNull($variables->getByPath('context.form')) ?: null,
            'disabled' => Typed::bool($variables->getByPath('context.disabled')),
            ComponentRefUtility::getAttributeName($clientBaseName, 'emptyValueInput') => $rootId,
        ]);

        return '<input ' . (string)$attributes . ' />';
    }
}
