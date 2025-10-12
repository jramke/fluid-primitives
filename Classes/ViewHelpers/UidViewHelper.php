<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Generates a unique identifier using `bin2hex(random_bytes(4))`.
 *
 * This is used internally for the default value for the `rootId` prop in components. Its exposed as a ViewHelper for convenience.
 *
 * ## Example
 * ```html
 * <f:variable name="myId">{ui:uid()}</f:variable>
 * ```
 * this will generate a unique id like `«42f10a7d»`
 */
class UidViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('prefix', 'string', 'The prefix of the generated ID', false, '');
    }

    public function render(): string
    {
        return ComponentUtility::uid($this->arguments['prefix']);
    }
}
