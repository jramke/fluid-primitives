<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Domain\Dto\RootComponentLocation;

/**
 * Lists every root component a {@see ComponentCollectionInterface} knows about - the inverse of
 * {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::resolveTemplateName()}, which
 * needs a name up front rather than being able to enumerate them. Walks
 * `getTemplatePaths()->getTemplateRootPaths()` for a `Root.fluid.html` directly inside each
 * immediate subfolder - the one template-naming convention every primitive in this codebase (and,
 * since `getTemplatePaths()` is a public interface method, any third-party collection following the
 * same convention) uses for its own root component.
 */
final class ComponentEnumerator
{
    /**
     * @return list<RootComponentLocation>
     */
    public static function enumerateRootComponents(ComponentCollectionInterface $collection): array
    {
        $locations = [];

        foreach ($collection->getTemplatePaths()->getTemplateRootPaths() as $templateRootPath) {
            $rootTemplates = glob(rtrim($templateRootPath, characters: '/') . '/*/Root.fluid.html') ?: [];

            foreach ($rootTemplates as $rootTemplate) {
                $folderPath = dirname($rootTemplate);
                $name = basename($folderPath);

                $locations[$name] = new RootComponentLocation($name, lcfirst($name) . '.root', $folderPath);
            }
        }

        ksort($locations);

        return array_values($locations);
    }
}
