<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Exception\TemplateErrorException;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Throws an exception with your own message when a condition is true.
 *
 * Use it in a component for a misconfiguration that argument definitions cannot express, such as missing slot content:
 *
 * ```html
 * <f:variable name="reviewText" value="{ui:slotFilled()}" />
 * <ui:error when="!{reviewText}" message="ui:productReview needs the review text as slot content." />
 * ```
 *
 */
class ErrorViewHelper extends AbstractViewHelper
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
        if (Typed::bool($this->arguments['when'])) {
            throw new TemplateErrorException(
                Typed::string($this->arguments['message']),
                Typed::int($this->arguments['code']),
            );
        }
    }
}
