<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Fixtures;

use Jramke\FluidPrimitives\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * Stands in for a userland component collection (e.g. packages/docs's own) whose components are thin
 * `ui:useProps` + `spreadProps` wrappers around a `primitives:` part - the exact shape every
 * Registry/docs component wrapper uses.
 */
final class UsePropsForwardingComponentCollection extends AbstractComponentCollection
{
    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths([__DIR__ . '/UsePropsForwarding']);
        return $templatePaths;
    }
}
