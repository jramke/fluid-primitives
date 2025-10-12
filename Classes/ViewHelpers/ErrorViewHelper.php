<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Throw an error with a custom message.
 * 
 * This is useful inside components to throw errors for required props or other misconfigurations that you cant handle with argument definitions.
 *
 * ## Example
 * ```html
 * <ui:error when="!{value}" message="This is an error message." />
 * ```
 */
class ErrorViewHelper extends AbstractConditionViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('when', 'bool', 'The condition that triggers the exception', true);
        $this->registerArgument('message', 'string', 'The exception message', false, 'An error occurred in Fluid.');
        $this->registerArgument('code', 'int', 'The exception code', false, 1759132287);
    }

    public function render(): void
    {
        if ((bool)$this->arguments['when']) {
            throw new \RuntimeException(
                $this->arguments['message'],
                (int)$this->arguments['code']
            );
        }
    }
}
