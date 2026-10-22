<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\ComponentRootUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Adds a root component to the client hydration data even when none of its tags carries [`ui:ref`](/docs/viewhelpers/ref).
 *
 * Place it in the template of the root component. In any other template it throws an exception:
 *
 * ```html
 * <ui:exposeToClient />
 * ```
 *
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

        if (!ComponentRootUtility::isDeclaredRootFromContext($renderingContext)) {
            throw new \RuntimeException(
                'The exposeToClient ViewHelper can only be used in a root component.',
                1754253447,
            );
        }

        if (!ComponentRootUtility::isRenderedAsRootFromContext($renderingContext)) {
            // Declared root, but this call delegates via spreadProps - whichever ancestor it's
            // rendered as root through is responsible for its own hydration bookkeeping.
            return '';
        }

        return Constants::MANUALLY_EXPOSED_TO_CLIENT_MARKER;
    }
}
