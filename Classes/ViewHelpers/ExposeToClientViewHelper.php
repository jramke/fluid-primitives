<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Expose a component to the client.
 *
 * This forces a component to be exposed to the client hydration data even if we did not used any [ui:ref](/docs/viewhelpers/ref) ViewHelper.
 *
 * ## Example
 * ```html
 * <ui:exposeToClient />
 * ...
 * ```
 */
class ExposeToClientViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void {}

    public function render(): string
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'ExposeToClient ViewHelper is missing its rendering context.',
            1_788_100_007,
        );

        if (!ComponentUtility::isComponent($renderingContext)) {
            throw new \RuntimeException(
                'The exposeToClient ViewHelper can only be used inside a component.',
                1754253446,
            );
        }

        if (!ComponentNameUtility::isRootComponent($renderingContext)) {
            throw new \RuntimeException(
                'The exposeToClient ViewHelper can only be used in a root component.',
                1754253447,
            );
        }

        return Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER;
    }
}
