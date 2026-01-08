<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolverFactoryInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinitionProviderInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;

#[Autoconfigure(public: true)]
class ComponentCollectionService
{
    public function __construct(
        private ViewHelperResolverFactoryInterface $viewHelperResolverFactory,
    ) {}

    public function getCollectionByViewHelperName(string $viewHelperName): ComponentDefinitionProviderInterface&ComponentTemplateResolverInterface
    {
        if (!str_contains($viewHelperName, ':')) {
            throw new RuntimeException(
                'Could not resolve component collection for ' . $viewHelperName . ', invalid ViewHelper name format, expected "namespace:name"',
                1767886410
            );
        }
        [$explodedNamespace, $explodedName] = explode(':', $viewHelperName);

        $viewHelperResolver = $this->viewHelperResolverFactory->create();
        $viewHelperResolverDelegate = $viewHelperResolver->getResponsibleDelegate(
            $explodedNamespace,
            $explodedName
        );
        if (!$viewHelperResolverDelegate) {
            throw new RuntimeException(
                'Could not resolve component collection for ' . $explodedNamespace . ':' . $explodedName . ', no ViewHelperResolverDelegate found',
                1767886411
            );
        }

        if (!$viewHelperResolverDelegate instanceof ComponentDefinitionProviderInterface) {
            throw new RuntimeException(
                'Could not resolve component collection for ' . $explodedNamespace . ':' . $explodedName . ', ViewHelperResolverDelegate does not implement ' . ComponentDefinitionProviderInterface::class,
                1767886412
            );
        }

        if (!$viewHelperResolverDelegate instanceof ComponentTemplateResolverInterface) {
            throw new RuntimeException(
                'Could not resolve component collection for ' . $explodedNamespace . ':' . $explodedName . ', ViewHelperResolverDelegate does not implement ' . ComponentTemplateResolverInterface::class,
                1767886413
            );
        }

        return $viewHelperResolverDelegate;
    }
}
