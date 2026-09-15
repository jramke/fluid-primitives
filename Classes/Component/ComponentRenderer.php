<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Domain\Dto\ComponentHydrationCandidate;
use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use Jramke\FluidPrimitives\Factory\ComponentRootContextFactory;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Service\Component\AsChildAttributeSpreader;
use Jramke\FluidPrimitives\Service\Component\CheckboxGroupContextVariableMerger;
use Jramke\FluidPrimitives\Service\Component\ComponentArgumentResolver;
use Jramke\FluidPrimitives\Service\Component\ComponentHydrationCollector;
use Jramke\FluidPrimitives\Service\Component\ComponentIdentityResolver;
use Jramke\FluidPrimitives\Service\Component\ContextMarkedPropsExposer;
use Jramke\FluidPrimitives\Service\Component\FieldContextVariableMerger;
use Jramke\FluidPrimitives\Service\ContextService;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\ViewHelpers\SlotViewHelper;

/**
 * Built exclusively through {@see \Jramke\FluidPrimitives\Factory\ComponentRendererFactory}, which
 * binds the component-collection-specific componentResolver at construction time - a fresh instance
 * per caller, never container-managed itself, so nothing here ever needs a post-construction write.
 */
// Cyclomatic complexity is summed across the whole class, not per method - renderComponent() is
// already split as far as the 5-parameter guideline on extracted methods allows (see its own
// docblock), so this doesn't indicate an actual decomposition opportunity, just an inherently
// branchy rendering pipeline (mirrors the existing @mago-expect lint:halstead on renderComponent()
// itself, for the same reason).
// @mago-expect lint:cyclomatic-complexity
final readonly class ComponentRenderer implements ComponentRendererInterface
{
    public function __construct(
        private ComponentCollectionInterface $componentResolver,
        private ComponentIdentityResolver $identityResolver,
        private ComponentArgumentResolver $argumentResolver,
        private ComponentRootContextFactory $rootContextFactory,
        private ContextMarkedPropsExposer $contextMarkedPropsExposer,
        private FieldContextVariableMerger $fieldContextVariableMerger,
        private CheckboxGroupContextVariableMerger $checkboxGroupContextVariableMerger,
        private ComponentHydrationCollector $hydrationCollector,
        private AsChildAttributeSpreader $asChildAttributeSpreader,
    ) {}

    /**
     * Renders a Fluid template to be used as a component. The necessary view configuration (template paths,
     * template name and possible additional variables) are expected to be provided by the component template
     * resolver.
     *
     * This is the single entry point every component render goes through, coordinating the 9 collaborators
     * above in sequence; {@see createView}, {@see prepareRenderState} and {@see renderComponentOutput} already
     * carry as much of that sequence as can be extracted without exceeding the 5-parameter guideline on the
     * extracted methods themselves (the remaining steps interleave too many of viewHelperName/arguments/
     * argumentDefinitions/renderingContext/parentRenderingContext/identity to split further without just
     * relocating the same parameter list one level down).
     *
     * @param array<string, mixed> $arguments
     * @param array<string, \Closure> $slots
     */
    // @mago-expect lint:halstead
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

        $view = $this->createView($renderingContext, $viewHelperName, $identity);

        // Expose additional arguments as tag attributes so they can be used by the ui:attributes view helper
        $this->argumentResolver->exposeAdditionalAttributes(
            $renderingContext,
            $arguments,
            $resolvedArguments->additionalArguments,
        );

        // render() call includes validation of provided arguments
        $view->assignMultiple($this->componentResolver->getAdditionalVariables($viewHelperName));

        // Expose variables as context so it can be picked up in other components rendered inside this component.
        if ($isRootComponent) {
            $this->rootContextFactory->create(
                $argumentDefinitions,
                $view,
                $viewHelperName,
                $parentRenderingContext,
                $this->componentResolver,
            );
        }

        $restoreExposedContext = null;
        if ($propsMarkedForContext !== [] && !$isRootComponent) {
            $restoreExposedContext = $this->contextMarkedPropsExposer->expose(
                $propsMarkedForContext,
                $arguments,
                $argumentDefinitions,
                $parentRenderingContext,
                $viewHelperName,
            );
        }

        $renderState = $this->prepareRenderState($parentRenderingContext, $view, $identity, $arguments);
        $ctx = $renderState['ctx'];

        $rendered = $this->renderComponentOutputAndRestoreContext(
            $view,
            $viewHelperName,
            $arguments,
            $slots,
            $restoreExposedContext,
        );

        if ($isRootComponent) {
            // cleanup the context variable from the parent rendering context
            ContextService::removeFromRenderingContext($parentRenderingContext, $identity->baseName);

            // Call afterRendering lifecycle method only for root or closed components
            if ($ctx && method_exists($ctx, 'afterRendering')) {
                $ctx->afterRendering($rendered);
            }

            $rendered = $this->hydrationCollector->collectForRootComponent(
                new ComponentHydrationCandidate(
                    $rendered,
                    $viewHelperName,
                    $renderingContext,
                    $identity->clientBaseName,
                    $arguments,
                    $argumentDefinitions,
                    $propsMarkedForClient,
                    $ctx,
                    array_filter(
                        [
                            'field' => $renderState['fieldRootId'],
                            'checkboxGroup' => $renderState['checkboxGroupRootId'],
                        ],
                        static fn(?string $rootId): bool => $rootId !== null,
                    ),
                    $renderState['portalSnapshot'],
                ),
            );
        }

        return $rendered;
    }

    /**
     * Merges Field/CheckboxGroup ancestor variables into this component (if applicable), resolves its
     * active context, exposes it to the view, runs its `beforeRendering` hook, and snapshots the portal
     * registry - everything the post-render step (afterRendering + hydration collection) needs to know
     * about this component's ambient rendering state.
     *
     * @param array<string, mixed> $arguments
     * @return array{ctx: ?AbstractComponentContext, fieldRootId: ?string, checkboxGroupRootId: ?string, portalSnapshot: array<string, string[]>}
     */
    private function prepareRenderState(
        RenderingContextInterface $parentRenderingContext,
        TemplateView $view,
        ComponentIdentity $identity,
        array &$arguments,
    ): array {
        $baseName = $identity->baseName;
        $isRootComponent = $identity->isRootComponent;

        // Expose other component contexts to allow deep nesting of composable components
        $otherComponentContexts = $this->getOtherComponentContexts($parentRenderingContext, $baseName);

        // Pick up potential context from current component (parent or itself if root)
        $ctx = $this->getRootComponentContext($parentRenderingContext, $baseName);

        $fieldRootId = null;
        if ($isRootComponent && $this->componentSupportsField($identity->clientBaseName)) {
            $fieldRootId = $this->fieldContextVariableMerger->apply(
                $otherComponentContexts,
                $identity->clientBaseName,
                $view,
                $arguments,
                $ctx,
            );
        }

        $checkboxGroupRootId = null;
        if ($isRootComponent && $baseName === 'checkbox') {
            $checkboxGroupRootId = $this->checkboxGroupContextVariableMerger->apply(
                $otherComponentContexts,
                $view,
                $arguments,
                $ctx,
            );
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

        return [
            'ctx' => $ctx,
            'fieldRootId' => $fieldRootId,
            'checkboxGroupRootId' => $checkboxGroupRootId,
            'portalSnapshot' => $portalRegistrySnapshotBeforeRender,
        ];
    }

    /**
     * @return array<string, ComponentContextInterface>
     */
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
            // Narrowed immediately below via instanceof - no Typed:: equivalent for objects.
            // @mago-expect analysis:mixed-assignment
            $ctx = $variableProvider->get('context');
        }

        return $ctx instanceof AbstractComponentContext ? $ctx : null;
    }

    protected function componentSupportsField(string $baseName): bool
    {
        return in_array($baseName, Constants::COMPONENTS_THAT_SUPPORT_FIELD, strict: true);
    }

    private function createView(
        RenderingContextInterface $renderingContext,
        string $viewHelperName,
        ComponentIdentity $identity,
    ): TemplateView {
        $view = new TemplateView($renderingContext);

        $view->assign('rootId', $identity->rootId);

        $view->getRenderingContext()->getVariableProvider()->remove('settings');
        $view->assign('settings', ComponentUtility::getSettings());

        $view->assign('component', [
            'fullName' => $viewHelperName,
            'baseName' => $identity->baseName,
            'isRoot' => $identity->isRootComponent,
            'isComposable' => $identity->isComposableComponent,
        ]);

        return $view;
    }

    /**
     * Renders this component's output, then reverts any context="{true}" props exposed for the
     * duration of this render (root and its slot/children) back to whatever they were before -
     * see {@see ContextMarkedPropsExposer::expose()}'s own docblock for why that revert matters.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, \Closure> $slots
     */
    private function renderComponentOutputAndRestoreContext(
        TemplateView $view,
        string $viewHelperName,
        array $arguments,
        array $slots,
        ?\Closure $restoreExposedContext,
    ): string {
        try {
            return $this->renderComponentOutput($view, $viewHelperName, $arguments, $slots);
        } finally {
            if ($restoreExposedContext instanceof \Closure) {
                $restoreExposedContext();
            }
        }
    }

    /**
     * Renders the component template, or - for `asChild` - spreads its resolved attributes onto its
     * rendered child instead (see {@see AsChildAttributeSpreader}).
     *
     * @param array<string, mixed> $arguments
     * @param array<string, \Closure> $slots
     */
    private function renderComponentOutput(
        TemplateView $view,
        string $viewHelperName,
        array $arguments,
        array $slots,
    ): string {
        $renderedComponent = (string)$view->render($this->componentResolver->resolveTemplateName($viewHelperName));

        if (!($arguments['asChild'] ?? false)) {
            return $renderedComponent;
        }

        $renderedChild = ($slots['default'] ?? null) !== null && is_callable($slots['default'])
            ? (string)$slots['default']()
            : '';

        return $this->asChildAttributeSpreader->spread($renderedChild, $renderedComponent);
    }
}
