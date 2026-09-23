<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Fluid\Core\ViewHelper\ViewHelperResolverFactoryInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinitionProviderInterface;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolverDelegateInterface;

#[Autoconfigure(public: true)]
class ComponentCollectionService
{
    public function __construct(
        private readonly ViewHelperResolverFactoryInterface $viewHelperResolverFactory,
    ) {}

    public function getCollectionByViewHelperName(string $viewHelperName): ComponentDefinitionProviderInterface&ComponentTemplateResolverInterface
    {
        if (!str_contains($viewHelperName, ':')) {
            throw new RuntimeException(
                'Could not resolve component collection for ' .
                $viewHelperName .
                ', invalid ViewHelper name format, expected "namespace:name"',
                1767886410,
            );
        }
        [$explodedNamespace, $explodedName] = explode(':', $viewHelperName);

        $viewHelperResolver = $this->viewHelperResolverFactory->create();
        $viewHelperResolverDelegate = $viewHelperResolver->getResponsibleDelegate($explodedNamespace, $explodedName);
        if (!$viewHelperResolverDelegate instanceof ViewHelperResolverDelegateInterface) {
            throw new RuntimeException(
                'Could not resolve component collection for ' .
                $explodedNamespace .
                ':' .
                $explodedName .
                ', no ViewHelperResolverDelegate found',
                1767886411,
            );
        }

        if (!$viewHelperResolverDelegate instanceof ComponentDefinitionProviderInterface) {
            throw new RuntimeException(
                'Could not resolve component collection for ' .
                $explodedNamespace .
                ':' .
                $explodedName .
                ', ViewHelperResolverDelegate does not implement ' .
                ComponentDefinitionProviderInterface::class,
                1767886412,
            );
        }

        if (!$viewHelperResolverDelegate instanceof ComponentTemplateResolverInterface) {
            throw new RuntimeException(
                'Could not resolve component collection for ' .
                $explodedNamespace .
                ':' .
                $explodedName .
                ', ViewHelperResolverDelegate does not implement ' .
                ComponentTemplateResolverInterface::class,
                1767886413,
            );
        }

        return $viewHelperResolverDelegate;
    }

    /**
     * Every registered Fluid component collection in the current installation - reads the same
     * namespace registry {@see getViewHelperNamespaceIdentifierByCollectionClassName()} does, just
     * in the opposite direction (every collection, not one looked up by class name).
     *
     * @return list<class-string<ComponentCollectionInterface>>
     */
    public function discoverCollections(): array
    {
        $viewHelperResolver = $this->viewHelperResolverFactory->create();
        $registeredNamespaces = $viewHelperResolver->getNamespaces();

        $collections = [];
        foreach ($registeredNamespaces as $delegateClassNames) {
            if (!is_array($delegateClassNames)) {
                continue;
            }

            foreach ($delegateClassNames as $delegateClassName) {
                if (
                    is_string($delegateClassName) &&
                    is_a($delegateClassName, ComponentCollectionInterface::class, allow_string: true)
                ) {
                    $collections[$delegateClassName] = $delegateClassName;
                }
            }
        }

        return array_values($collections);
    }

    public function getViewHelperNamespaceIdentifierByCollectionClassName(string $collectionClassName): ?string
    {
        $viewHelperResolver = $this->viewHelperResolverFactory->create();
        $registeredNamespaces = $viewHelperResolver->getNamespaces();

        if ($registeredNamespaces === []) {
            return null;
        }

        foreach ($registeredNamespaces as $namespaceIdentifier => $delegateClassNames) {
            if (!is_array($delegateClassNames)) {
                continue;
            }

            foreach ($delegateClassNames as $delegateClassName) {
                // $collectionClassName is always a real class-string in practice (every caller passes
                // AbstractComponentCollection::getNamespace()'s static::class), but it only reaches
                // here as plain `string` because it flows through Fluid's own
                // ViewHelperResolverDelegateInterface::getNamespace(): string, which we can't tighten.
                // is_a() with allow_string degrades safely to `false` if it's ever not one.
                // @mago-expect analysis:possibly-invalid-argument
                if (
                    is_string($delegateClassName) && is_a($delegateClassName, $collectionClassName, allow_string: true)
                ) {
                    return $namespaceIdentifier;
                }
            }
        }

        return null;
    }
}
