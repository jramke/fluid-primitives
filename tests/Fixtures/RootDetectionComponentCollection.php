<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * Stands in for a userland component collection exercising every root-component folder shape:
 * classic Root.html + parts (Widget/), a nested example folder with no Root sibling
 * (Widget/Examples/), a flat multi-file folder with no Root and no matching filename (Icon/), and
 * a tiered classic root (Tier/Widget/Root.fluid.html).
 */
class RootDetectionComponentCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([__DIR__ . '/RootDetection']);
        return $templatePaths;
    }
}
