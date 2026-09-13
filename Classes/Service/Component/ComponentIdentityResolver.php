<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Resolves a component call's basic identity from its ViewHelper name and arguments: whether it's a
 * root or composable (subcomponent) component, its rootId, and its base name (e.g. "accordion" for
 * both "Accordion.Root" and "Accordion.Item").
 */
final readonly class ComponentIdentityResolver
{
    /**
     * @param array<string, mixed> $arguments
     */
    public function resolve(
        string $viewHelperName,
        array $arguments,
        RenderingContextInterface $renderingContext,
    ): ComponentIdentity {
        $isRootComponent = ComponentNameUtility::isRootComponent($viewHelperName);
        if (isset($arguments['spreadProps']) && $arguments['spreadProps'] === true) {
            $isRootComponent = false;
        }

        $isComposableComponent = ComponentNameUtility::isComposableComponent($viewHelperName);

        $rootId = $arguments['rootId'] ?? null;
        if (!isset($rootId)) {
            if ($isRootComponent) {
                $rootId = ComponentUtility::id();
            } else {
                // We assign the rootId to each rendered component so this line gets the rootId of the parent component when rendering subcomponents.
                $rootId = $renderingContext->getVariableProvider()->get('rootId') ?? null;
            }
        }

        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

        return new ComponentIdentity($isRootComponent, $isComposableComponent, $rootId, $baseName);
    }
}
