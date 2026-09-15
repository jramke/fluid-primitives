<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * Stands in for a userland component collection whose components have no client hydration at all -
 * a plain server-rendered `Card` with just a root `<div>` and no `ui:ref`.
 */
final class PlainComponentCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([__DIR__ . '/PlainComponent']);
        return $templatePaths;
    }
}
