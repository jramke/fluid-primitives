<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * A single root component exercising {@see \Jramke\FluidPrimitives\Service\Component\ClientPropValueResolver}'s
 * fail-fast paths: a required (no default, `optional="{false}"`) client prop, and an object-typed
 * client prop with no {@see \Jramke\FluidPrimitives\Contracts\ClientTypeAwareInterface}/converter.
 */
final class HydrationEdgeCasesCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([__DIR__ . '/HydrationEdgeCases']);
        return $templatePaths;
    }
}
