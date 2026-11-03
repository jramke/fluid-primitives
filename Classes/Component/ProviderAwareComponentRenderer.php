<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\ProvidedRootArgumentException;
use Jramke\FluidPrimitives\Factory\ComponentRootContextFactory;
use Jramke\FluidPrimitives\Registry\ProvidedRootRegistry;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentProviderUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Handles what decides where a component's context comes from before {@see ComponentRenderer} renders it:
 * a `<component>.provider` registers the context of its root and renders nothing, a root with `provider`
 * takes its `rootId` from it, and a detachable part with `provider` renders with that context as if it
 * were inside its root.
 */
final readonly class ProviderAwareComponentRenderer implements ComponentRendererInterface
{
    public function __construct(
        private ComponentRendererInterface $renderer,
        private ComponentCollectionInterface $componentResolver,
        private ComponentRootContextFactory $rootContextFactory,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     * @param array<string, \Closure> $slots
     */
    public function renderComponent(
        string $viewHelperName,
        array $arguments,
        array $slots,
        RenderingContextInterface $parentRenderingContext,
    ): string {
        $providedRoot = ComponentProviderUtility::resolveProvidedRoot($viewHelperName, $this->componentResolver);
        if ($providedRoot !== null) {
            $this->rootContextFactory->provide(
                $providedRoot,
                $arguments,
                $parentRenderingContext,
                $this->componentResolver,
            );
            return '';
        }

        $providerId = Typed::string($arguments[Constants::PROP_PROVIDER] ?? null);
        if ($providerId === '') {
            return $this->renderer->renderComponent($viewHelperName, $arguments, $slots, $parentRenderingContext);
        }

        $definitions = $this->componentResolver->getComponentDefinition($viewHelperName)->getArgumentDefinitions();
        if (!array_key_exists(Constants::PROP_PROVIDER, $definitions)) {
            throw new InvalidPropValueException(
                sprintf(
                    '<%s> takes no provider. Only a root with parts and a part whose template has <ui:detachable /> link to a provider.',
                    $viewHelperName,
                ),
                1_789_100_010,
            );
        }

        $isRoot = $this->componentResolver->isDeclaredRoot($viewHelperName);
        $baseName = ComponentNameUtility::getComponentBaseNameFromViewHelperName($viewHelperName, $isRoot);
        $provided = ProvidedRootRegistry::require(
            [$this->componentResolver->getNamespace()],
            $baseName,
            $providerId,
            '<' . $viewHelperName . '>',
        );

        if ($isRoot) {
            // A root that forwards with spreadProps is the inner call of a wrapper, not a root of its own
            if ((Typed::arrayOrNull($arguments['spreadProps'] ?? null) ?? []) !== []) {
                throw new ProvidedRootArgumentException(
                    sprintf(
                        'The %s with provider "%s" forwards its props with spreadProps, so the component around it is its root. Give provider="%s" to that component.',
                        $baseName,
                        $providerId,
                        $providerId,
                    ),
                    1_789_100_008,
                );
            }

            if (($arguments[Constants::PROP_ROOT_ID] ?? null) !== null) {
                throw new ProvidedRootArgumentException(
                    sprintf(
                        'The %s with provider "%s" takes its rootId from <%s.provider id="%s">. Remove its rootId.',
                        $baseName,
                        $providerId,
                        $baseName,
                        $providerId,
                    ),
                    1_789_100_007,
                );
            }

            return $this->renderer->renderComponent(
                $viewHelperName,
                [...$arguments, Constants::PROP_ROOT_ID => $providerId],
                $slots,
                $parentRenderingContext,
            );
        }

        return ContextService::withContext(
            $parentRenderingContext,
            $baseName,
            $provided->context,
            fn(): string => $this->renderer->renderComponent(
                $viewHelperName,
                $arguments,
                $slots,
                $parentRenderingContext,
            ),
        );
    }
}
