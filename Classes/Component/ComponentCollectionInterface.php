<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use TYPO3Fluid\Fluid\Core\Component\ComponentDefinition;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinitionProviderInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolverDelegateInterface;

/**
 * Interface for the fluid-primitives component collection class.
 */
interface ComponentCollectionInterface extends
    ViewHelperResolverDelegateInterface,
    ComponentDefinitionProviderInterface,
    ComponentTemplateResolverInterface
{
    /**
     * Fetches the component definition (arguments, slots) for a ViewHelper call by
     * parsing the underlying Fluid template
     */
    public function getComponentDefinition(string $viewHelperName): ComponentDefinition;

    /**
     * Whether $viewHelperName's own declared shape (folder-shape default included) is root - see
     * {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility::isDeclaredRootFromViewHelperName()}
     * for the underlying rule this reads back from an already-parsed {@see getComponentDefinition()}.
     */
    public function isDeclaredRoot(string $viewHelperName): bool;

    /**
     * @return array<string>
     */
    public function getContextNamespaces(): array;
}
