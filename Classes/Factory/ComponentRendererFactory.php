<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Factory;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Component\ComponentRenderer;
use Jramke\FluidPrimitives\Service\Component\AsChildAttributeSpreader;
use Jramke\FluidPrimitives\Service\Component\CheckboxGroupContextVariableMerger;
use Jramke\FluidPrimitives\Service\Component\ComponentArgumentResolver;
use Jramke\FluidPrimitives\Service\Component\ComponentHydrationCollector;
use Jramke\FluidPrimitives\Service\Component\ComponentIdentityResolver;
use Jramke\FluidPrimitives\Service\Component\ContextMarkedPropsExposer;
use Jramke\FluidPrimitives\Service\Component\FieldContextVariableMerger;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;

/**
 * Builds a {@see ComponentRenderer} bound to the given component collection. Public so
 * {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::getComponentRenderer()} can
 * fetch it via GeneralUtility::makeInstance() from outside the container's own graph - unlike
 * ComponentRenderer itself, this factory holds no per-call state (componentResolver is a create()
 * parameter, not a stored dependency), so it's safe to leave container-shared.
 */
#[Autoconfigure(public: true)]
final readonly class ComponentRendererFactory
{
    public function __construct(
        private ComponentIdentityResolver $identityResolver,
        private ComponentArgumentResolver $argumentResolver,
        private ComponentRootContextFactory $rootContextFactory,
        private ContextMarkedPropsExposer $contextMarkedPropsExposer,
        private FieldContextVariableMerger $fieldContextVariableMerger,
        private CheckboxGroupContextVariableMerger $checkboxGroupContextVariableMerger,
        private ComponentHydrationCollector $hydrationCollector,
        private AsChildAttributeSpreader $asChildAttributeSpreader,
    ) {}

    public function create(ComponentCollectionInterface $componentResolver): ComponentRendererInterface
    {
        return new ComponentRenderer(
            $componentResolver,
            $this->identityResolver,
            $this->argumentResolver,
            $this->rootContextFactory,
            $this->contextMarkedPropsExposer,
            $this->fieldContextVariableMerger,
            $this->checkboxGroupContextVariableMerger,
            $this->hydrationCollector,
            $this->asChildAttributeSpreader,
        );
    }
}
