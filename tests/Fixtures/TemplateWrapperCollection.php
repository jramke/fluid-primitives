<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * A userland root component whose own template wraps a real combobox and a `ui:template` stencil of
 * it - the shape of a docs example - so the wrapper's own `rootId` variable is in scope around the
 * stencil.
 */
final class TemplateWrapperCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([__DIR__ . '/TemplateWrapper']);
        return $templatePaths;
    }
}
