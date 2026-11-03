<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Factory;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Domain\Dto\ProvidedRoot;
use Jramke\FluidPrimitives\Registry\ProvidedRootRegistry;
use Jramke\FluidPrimitives\Service\Component\ComponentArgumentResolver;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\StrictArgumentProcessor;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3Fluid\Fluid\View\TemplateView;

/**
 * Creates a root component's own {@see ComponentContextInterface} from the variables of its view. The
 * renderer pushes it onto the {@see \Jramke\FluidPrimitives\Service\ContextService} stack, a
 * `<component>.provider` keeps it for later.
 */
final readonly class ComponentRootContextFactory
{
    public function __construct(
        private ComponentContextFactory $contextFactory,
        private ComponentArgumentResolver $argumentResolver,
    ) {}

    /**
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
    public function create(
        array $argumentDefinitions,
        TemplateView $view,
        string $viewHelperName,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
    ): ComponentContextInterface {
        $contextVariables = $this->buildContextVariables(
            $argumentDefinitions,
            $view->getRenderingContext()->getVariableProvider(),
        );
        $contextClassName = ComponentUtility::getContextClassNameFromViewHelperName(
            $componentResolver->resolveTemplateName($viewHelperName),
            $componentResolver->getContextNamespaces(),
        );
        return $this->contextFactory->create(
            $contextClassName,
            $view->getRenderingContext(),
            $parentRenderingContext,
            $componentResolver,
            $contextVariables,
        );
    }

    /**
     * Creates the context of `$rootViewHelperName` from the arguments of its `<component>.provider` and
     * registers it for the root and its parts, without rendering anything.
     *
     * @param array<string, mixed> $arguments
     */
    public function provide(
        string $rootViewHelperName,
        array $arguments,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
    ): void {
        // The provider's `id` is the rootId of its root
        $rootId = Typed::string($arguments['id'] ?? null);
        ComponentUtility::assertValidRootId($rootId);
        unset($arguments['id']);
        $arguments[Constants::PROP_ROOT_ID] = $rootId;

        $argumentDefinitions = $componentResolver
            ->getComponentDefinition($rootViewHelperName)
            ->getArgumentDefinitions();
        $arguments = $this->argumentResolver->resolve(
            $arguments,
            $argumentDefinitions,
            $parentRenderingContext,
            $parentRenderingContext,
        )->arguments;

        $renderingContext = clone $parentRenderingContext;
        $renderingContext->setVariableProvider($renderingContext
            ->getVariableProvider()
            ->getScopeCopy([
                ...$arguments,
                ...$componentResolver->getAdditionalVariables($rootViewHelperName),
            ]));
        $renderingContext->setViewHelperVariableContainer(new ViewHelperVariableContainer());

        ProvidedRootRegistry::provide(
            $componentResolver->getNamespace(),
            ComponentNameUtility::getComponentBaseNameFromViewHelperName($rootViewHelperName, isDeclaredRoot: true),
            $rootId,
            new ProvidedRoot($arguments, $this->create(
                $argumentDefinitions,
                new TemplateView($renderingContext),
                $rootViewHelperName,
                $parentRenderingContext,
                $componentResolver,
            )),
        );
    }

    /**
     * A `<component>.provider` created the context where it was rendered. Its root binds it to where the
     * root is, so what the context reads from around it (a Form, a Fieldset) is what is around the root.
     */
    public function bind(
        ProvidedRoot $provided,
        TemplateView $view,
        RenderingContextInterface $parentRenderingContext,
        ComponentCollectionInterface $componentResolver,
    ): ComponentContextInterface {
        $provided->context->initialize(
            $view->getRenderingContext(),
            $parentRenderingContext,
            $componentResolver,
            $provided->context->getAllVariables(),
        );

        return $provided->context;
    }

    // This is somewhat what is already done by the template view when we call the render method but we need the variables earlier so we can expose them to the context.
    // We also dont throw anything here as the validation is handled by the mentioned render method.
    /**
     * @param array<string, ArgumentDefinition> $argumentDefinitions
     */
    private function buildContextVariables(
        array $argumentDefinitions,
        VariableProviderInterface $variableProvider,
    ): array {
        $argumentProcessor = new StrictArgumentProcessor();

        $variablesToRemove = [
            'component',
            'settings',
            'context',
            'globals',
            'class',
            'asChild',
        ];

        $contextVariables = $variableProvider->getAll();
        if (!is_array($contextVariables)) {
            // VariableProviderInterface::getAll() is contractually allowed to return an ArrayAccess
            // instance instead of a plain array, but every context variable downstream of this method
            // (and the rest of this class's own array<string, mixed> plumbing) assumes a real array -
            // fail clearly here rather than silently misbehaving if a custom VariableProvider is ever
            // introduced that actually exercises that part of the contract.
            throw new \LogicException(
                'Expected VariableProviderInterface::getAll() to return an array, got an ArrayAccess instance.',
                1_788_100_014,
            );
        }

        foreach ($argumentDefinitions as $argumentDefinition) {
            $argumentName = $argumentDefinition->getName();

            if (!$variableProvider->exists($argumentName)) {
                if ($argumentDefinition->isRequired()) {
                    continue; // Skip required arguments that are not provided
                }

                $contextVariables[$argumentName] = $argumentDefinition->getDefaultValue();
                continue;
            }

            // StrictArgumentProcessor::process() is Fluid core's own generic argument coercion - its
            // result is inherently mixed, matching isValid()'s own mixed acceptance right below.
            // @mago-expect analysis:mixed-assignment
            $processedValue = $argumentProcessor->process($variableProvider->get($argumentName), $argumentDefinition);
            if (!$argumentProcessor->isValid($processedValue, $argumentDefinition)) {
                continue; // Skip invalid values
            }
            $contextVariables[$argumentName] = $processedValue;
        }

        foreach ($variablesToRemove as $var) {
            unset($contextVariables[$var]);
        }

        return $contextVariables;
    }
}
