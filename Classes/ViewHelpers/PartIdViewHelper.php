<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\EnumUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates the ID for a component part.
 *
 * If an explicit ID was provided via the `ids` prop of the component, that ID will be used.
 * Otherwise, a deterministic ID is generated based on the component name, root ID and part name,
 * following the same convention as the zag-js DOM helpers:
 * `{componentName}:{rootId}:{part}` or `{componentName}:{rootId}:{part}:{value}` for multi-instance parts.
 *
 * ## Example
 * ```html
 * <div id="{ui:partId(part: 'root')}">...</div>
 * ```
 * This will generate: `id="field:«f1»"` (or the user-provided ID if set via `ids` prop).
 * The root part omits the part name suffix, matching the zag-js convention.
 *
 * For multi-instance parts (e.g. accordion items, tab panels):
 * ```html
 * <div id="{ui:partId(part: 'item-trigger', value: value)}">...</div>
 * ```
 * This will generate: `id="accordion:«f1»:item-trigger:my-value"`
 */
class PartIdViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('part', 'string', 'The part name of the component', true);
        $this->registerArgument(
            'value',
            'string|BackedEnum|UnitEnum|null|array',
            'Optional value for multi-instance parts (e.g. accordion items, tab triggers)',
            false,
            null,
        );
    }

    public function render(): string
    {
        if (!ComponentUtility::isComponent($this->renderingContext)) {
            throw new \RuntimeException(
                'The partId ViewHelper can only be used inside a component context. Make sure it is placed inside a fluid-primitives component template file.',
                1752000001,
            );
        }

        $part = $this->arguments['part'];
        $value = EnumUtility::normalize($this->arguments['value']);

        $componentName = ComponentUtility::getComponentBaseNameFromContext($this->renderingContext);
        $rootId = ComponentUtility::getRootIdFromContext($this->renderingContext);

        if (!$rootId) {
            throw new \RuntimeException(
                'No rootId found for component ' .
                ComponentUtility::getComponentFullNameFromContext($this->renderingContext) .
                '.',
                1752000002,
            );
        }

        // Check for explicit ID override from the ids prop
        $ids = $this->renderingContext->getVariableProvider()->getByPath('context.ids') ?? [];
        $idsArray = is_array($ids) ? $ids : [];

        return ComponentUtility::generatePartId($componentName, $rootId, $part, $value, $idsArray);
    }
}
