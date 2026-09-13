<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\ViewHelpers\SlotViewHelper;

final readonly class ComponentRenderer implements ComponentRendererInterface
{
    private ComponentIdentityResolver $identityResolver;

    private ComponentArgumentResolver $argumentResolver;

    private ComponentRootContextFactory $rootContextFactory;

    private ContextMarkedPropsExposer $contextMarkedPropsExposer;

    private FieldContextVariableMerger $fieldContextVariableMerger;

    private CheckboxGroupContextVariableMerger $checkboxGroupContextVariableMerger;

    private ComponentHydrationCollector $hydrationCollector;

    private AsChildAttributeSpreader $asChildAttributeSpreader;

    public function __construct(
        private ComponentCollectionInterface $componentResolver,
    ) {
        $this->identityResolver = new ComponentIdentityResolver();
        $this->argumentResolver = new ComponentArgumentResolver();
        $this->rootContextFactory = new ComponentRootContextFactory($componentResolver);
        $this->contextMarkedPropsExposer = new ContextMarkedPropsExposer();
        $this->fieldContextVariableMerger = new FieldContextVariableMerger();
        $this->checkboxGroupContextVariableMerger = new CheckboxGroupContextVariableMerger();
        $this->hydrationCollector = new ComponentHydrationCollector();
        $this->asChildAttributeSpreader = new AsChildAttributeSpreader();
    }

    /**
     * Renders a Fluid template to be used as a component. The necessary view configuration (template paths,
     * template name and possible additional variables) are expected to be provided by the component template
     * resolver.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, \Closure> $slots
     */
    public function renderComponent(
        string $viewHelperName,
        array $arguments,
        array $slots,
        RenderingContextInterface $parentRenderingContext,
    ): string {
        // Create new rendering context while retaining some global context (e. g. a possible request variable
        // or globally registered ViewHelper namespaces)
        $renderingContext = clone $parentRenderingContext;
        $renderingContext->getTemplateCompiler()->reset();
        $renderingContext->setTemplatePaths($this->componentResolver->getTemplatePaths());
        $renderingContext->setViewHelperResolver($renderingContext->getViewHelperResolver()->getScopedCopy());

        $identity = $this->identityResolver->resolve($viewHelperName, $arguments, $renderingContext);
        $isRootComponent = $identity->isRootComponent;
        $isComposableComponent = $identity->isComposableComponent;
        $rootId = $identity->rootId;
        $baseName = $identity->baseName;

        $argumentDefinitions = $this->componentResolver
            ->getComponentDefinition($viewHelperName)
            ->getArgumentDefinitions();

        $resolvedArguments = $this->argumentResolver->resolve(
            $arguments,
            $argumentDefinitions,
            $renderingContext,
            $parentRenderingContext,
        );
        $arguments = $resolvedArguments->arguments;
        $propsMarkedForClient = $resolvedArguments->propsMarkedForClient;
        $propsMarkedForContext = $resolvedArguments->propsMarkedForContext;

        // Set all components arguments
        $renderingContext->setVariableProvider($renderingContext->getVariableProvider()->getScopeCopy($arguments));

        // Provide slots to SlotViewHelper
        $renderingContext->setViewHelperVariableContainer(new ViewHelperVariableContainer());
        $renderingContext->getViewHelperVariableContainer()->addAll(SlotViewHelper::class, $slots);

        // Create Fluid view for component
        $view = new TemplateView($renderingContext);

        $view->assign('rootId', $rootId);

        $view->getRenderingContext()->getVariableProvider()->remove('settings');
        $view->assign('settings', ComponentUtility::getSettings());

        $componentData = [
            'fullName' => $viewHelperName,
            'baseName' => $baseName,
            'isRoot' => $isRootComponent,
            'isComposable' => $isComposableComponent,
        ];
        $view->assign('component', $componentData);

        // Expose additional arguments as tag attributes so they can be used by the ui:attributes view helper
        $this->argumentResolver->exposeAdditionalAttributes($renderingContext, $arguments, $resolvedArguments->additionalArguments);

        // render() call includes validation of provided arguments
        $view->assignMultiple($this->componentResolver->getAdditionalVariables($viewHelperName));

        // Expose variables as context so it can be picked up in other components rendered inside this component.
        if ($isRootComponent) {
            $this->rootContextFactory->create($argumentDefinitions, $view, $viewHelperName, $renderingContext, $parentRenderingContext);
        }

        if ($propsMarkedForContext !== [] && !$isRootComponent) {
            $this->contextMarkedPropsExposer->expose(
                $propsMarkedForContext,
                $arguments,
                $argumentDefinitions,
                $parentRenderingContext,
                $viewHelperName,
            );
        }

        // Expose other component contexts to allow deep nesting of composable components
        $otherComponentContexts = $this->getOtherComponentContexts($parentRenderingContext, $baseName);

        // Pick up potential context from current component (parent or itself if root)
        $ctx = $this->getRootComponentContext($parentRenderingContext, $baseName);

        $fieldRootId = null;
        if ($isRootComponent && $this->componentSupportsField($baseName)) {
            $fieldRootId = $this->fieldContextVariableMerger->apply($otherComponentContexts, $baseName, $view, $arguments, $ctx);
        }

        $checkboxGroupRootId = null;
        if ($isRootComponent && $baseName === 'checkbox') {
            $checkboxGroupRootId = $this->checkboxGroupContextVariableMerger->apply($otherComponentContexts, $view, $arguments, $ctx);
        }

        // Assign context if available
        if ($ctx instanceof AbstractComponentContext) {
            $view->assign('context', $ctx);
        }

        // Call beforeRendering lifecycle method only for root or closed components
        if ($ctx && $isRootComponent && method_exists($ctx, 'beforeRendering')) {
            $ctx->beforeRendering();
        }

        // ui:portal renders empty at its own position and buffers its real markup in PortalRegistry
        // for ui:portalContainer to flush elsewhere, so a root component whose every ref'd part sits
        // behind a portal (e.g. a triggerless Dialog/Popover - everything portaled, nothing rendered
        // inline) would otherwise never contain the data-scope="..." string ComponentHydrationCollector
        // looks for. Snapshotting the registry lets it also search whatever this render pass portaled away.
        $portalRegistrySnapshotBeforeRender = $isRootComponent ? PortalRegistry::getAll() : [];

        if ($arguments['asChild'] ?? false) {
            $renderedChild = isset($slots['default']) && is_callable($slots['default'])
                ? (string)$slots['default']()
                : '';
            $renderedComponent = (string)$view->render($this->componentResolver->resolveTemplateName($viewHelperName));
            $rendered = $this->asChildAttributeSpreader->spread($renderedChild, $renderedComponent);
        } else {
            $rendered = (string)$view->render($this->componentResolver->resolveTemplateName($viewHelperName));
        }

        if ($isRootComponent) {
            // cleanup the context variable from the parent rendering context
            ContextService::removeFromRenderingContext($parentRenderingContext, $baseName);

            // Call afterRendering lifecycle method only for root or closed components
            if ($ctx && method_exists($ctx, 'afterRendering')) {
                $ctx->afterRendering($rendered);
            }

            $rendered = $this->hydrationCollector->collectForRootComponent(new ComponentHydrationCandidate(
                $rendered,
                $viewHelperName,
                $renderingContext,
                $baseName,
                $arguments,
                $argumentDefinitions,
                $propsMarkedForClient,
                $ctx,
                ['field' => $fieldRootId, 'checkboxGroup' => $checkboxGroupRootId],
                $portalRegistrySnapshotBeforeRender,
            ));
        }

        return $rendered;
    }

    protected function getOtherComponentContexts(
        RenderingContextInterface $parentRenderingContext,
        string $baseName,
    ): array {
        $contexts = [];

        $allContexts = ContextService::getAllFromRenderingContext($parentRenderingContext);
        foreach ($allContexts as $ctxBaseName => $ctx) {
            if ($ctxBaseName === $baseName) {
                continue;
            }
            $contexts[$ctxBaseName] = $ctx;
        }

        return $contexts;
    }

    // We try to get the context from the ViewHelperVariableContainer first as that is the more reliable way in case of nested components
    // But to support the spreadProps pattern to use primitives we also need to check the context from the variable provider,
    // because we only expose the context to the ViewHelperVariableContainer when rendering the root component.
    protected function getRootComponentContext(
        RenderingContextInterface $renderingContext,
        string $baseName,
    ): ?AbstractComponentContext {
        $variableProvider = $renderingContext->getVariableProvider();

        $ctx = ContextService::getFromRenderingContext($renderingContext, $baseName);

        if (
            !$ctx instanceof ComponentContextInterface &&
            $variableProvider->getByPath('component.baseName') === $baseName
        ) {
            $ctx = $variableProvider->get('context') ?? null;
        }

        return $ctx;
    }

    protected function componentSupportsField(string $baseName): bool
    {
        return in_array($baseName, Constants::COMPONENTS_THAT_SUPPORT_FIELD, true);
    }
}
