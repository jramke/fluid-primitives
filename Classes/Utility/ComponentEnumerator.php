<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Domain\Dto\RootComponentLocation;

/**
 * Lists every root component a {@see ComponentCollectionInterface} knows about - the inverse of
 * {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::resolveTemplateName()}, which
 * needs a name up front rather than being able to enumerate them. Walks
 * `getTemplatePaths()->getTemplateRootPaths()` for each immediate subfolder, recognizing both of
 * `resolveTemplateName()`'s own on-disk shapes for a root component: a two-part `name.root`
 * registration's `Root.fluid.html`, and a single-part (dot-less) registration's `<Folder>/
 * <Folder>.fluid.html` (`ComponentNameUtility::isRootComponent()` treats every dot-less name as
 * root too) - the two template-naming conventions every primitive in this codebase (and, since
 * `getTemplatePaths()` is a public interface method, any third-party collection following the same
 * conventions) uses for its own root component.
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
            $subfolders = glob(rtrim($templateRootPath, characters: '/') . '/*', flags: GLOB_ONLYDIR) ?: [];

            foreach ($subfolders as $folderPath) {
                $name = basename($folderPath);
                $location = self::resolveLocation($folderPath, $name);
                if ($location !== null) {
                    $locations[$name] = $location;
                }
            }
        }

        ksort($locations);

        return array_values($locations);
    }

    /**
     * Two-part `name.root` shape first - `Root.fluid.html` - then the single-part shape,
     * `<Folder>/<Folder>.fluid.html`, i.e. the folder's own name reused as the file; null when
     * neither is present (a subfolder holding only non-root subcomponent templates).
     */
    private static function resolveLocation(string $folderPath, string $name): ?RootComponentLocation
    {
        if (is_file($folderPath . '/Root.fluid.html')) {
            return new RootComponentLocation($name, lcfirst($name) . '.root', $folderPath);
        }

        if (is_file($folderPath . '/' . $name . '.fluid.html')) {
            return new RootComponentLocation($name, lcfirst($name), $folderPath);
        }

        return null;
    }
}
