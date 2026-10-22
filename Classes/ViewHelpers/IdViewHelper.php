<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Generates an id that is unique within the request.
 *
 * ```html
 * <f:variable name="triggerId" value="{ui:id()}" />
 * ```
 *
 * The id starts with the `prefix` (`f` by default) and is wrapped in `«»`, for example `«fq7Zr2LpA1»`. Use it when two components share an element and have to agree on its id, as the collapsible and the tooltip do in [Composition](/docs/core-concepts/composition). It is meant for ids in markup, not for secrets.
 *
 */
class IdViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('prefix', 'string', 'The prefix of the generated ID', false, 'f');
    }

    public function render(): string
    {
        return ComponentUtility::id(Typed::string($this->arguments['prefix']));
    }
}
