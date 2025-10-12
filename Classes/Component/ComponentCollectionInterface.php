<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolverDelegateInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinitionProviderInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinition;

/**
 * Interface for the fluid-primitives component collection class.
 */
interface ComponentCollectionInterface extends ViewHelperResolverDelegateInterface, ComponentDefinitionProviderInterface, ComponentTemplateResolverInterface
{
    /**
     * Fetches the component definition (arguments, slots) for a ViewHelper call by
     * parsing the underlying Fluid template
     */
    public function getComponentDefinition(string $viewHelperName): ComponentDefinition;
}
