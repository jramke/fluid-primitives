<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates a base36 identifier that is unique per request.
 *
 * This is used internally for the default value for the `rootId` prop in components. Its exposed as a ViewHelper for convenience.
 * This ViewHelper can be used whenever you need a unique ID in your templates and should not be used for cryptographic purposes.
 *
 * ## Example
 * ```html
 * <f:variable name="myId">{ui:id()}</f:variable>
 * ```
 * this will generate a unique id like `«f4»`
 */
class IdViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('prefix', 'string', 'The prefix of the generated ID', false, '');
    }

    public function render(): string
    {
        return ComponentUtility::id($this->arguments['prefix']);
    }
}
