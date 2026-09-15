<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service\Component;

use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
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
        if (($arguments['spreadProps'] ?? null) === true) {
            $isRootComponent = false;
        }

        $isComposableComponent = ComponentNameUtility::isComposableComponent($viewHelperName);

        $rootId = Typed::stringOrNull($arguments['rootId'] ?? null);
        if ($rootId === null) {
            // For a non-root component we assign the rootId of the parent component when rendering subcomponents.
            $rootId = $isRootComponent
                ? ComponentUtility::id()
                : Typed::stringOrNull($renderingContext->getVariableProvider()->get('rootId'));
        }

        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName);
        $clientBaseName = ComponentNameUtility::camelCaseToLowerCaseDashed($baseName);

        return new ComponentIdentity($isRootComponent, $isComposableComponent, $rootId, $baseName, $clientBaseName);
    }
}
