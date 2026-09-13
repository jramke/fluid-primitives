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
    private ComponentArgumentResolver $argumentResolver;

    private ComponentContextPropagator $contextPropagator;

    private ComponentHydrationCollector $hydrationCollector;

    public function __construct(
        private ComponentCollectionInterface $componentResolver,
    ) {
        $this->argumentResolver = new ComponentArgumentResolver();
        $this->contextPropagator = new ComponentContextPropagator($componentResolver);
        $this->hydrationCollector = new ComponentHydrationCollector();
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

        $isRootComponent = ComponentUtility::isRootComponent($viewHelperName);
        if (isset($arguments['spreadProps']) && $arguments['spreadProps'] === true) {
            $isRootComponent = false;
        }

        $isComposableComponent = ComponentUtility::isComposableComponent($viewHelperName);

        $rootId = $arguments['rootId'] ?? null;
        if (!isset($rootId)) {
            if ($isRootComponent) {
                $rootId = ComponentUtility::id();
            } else {
                // We assign the rootId to each rendered component so this line gets the rootId of the parent component when rendering subcomponents.
                $rootId = $renderingContext->getVariableProvider()->get('rootId') ?? null;
            }
        }

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

        $baseName = ComponentUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

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
            $this->contextPropagator->createRootContext($argumentDefinitions, $view, $viewHelperName, $renderingContext, $parentRenderingContext);
        }

        if ($propsMarkedForContext !== [] && !$isRootComponent) {
            $this->contextPropagator->exposePropsMarkedForContext(
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
            $fieldRootId = $this->contextPropagator->applyFieldContextVariables($otherComponentContexts, $baseName, $view, $arguments, $ctx);
        }

        $checkboxGroupRootId = null;
        if ($isRootComponent && $baseName === 'checkbox') {
            $checkboxGroupRootId = $this->contextPropagator->applyCheckboxGroupContextVariables($otherComponentContexts, $view, $arguments, $ctx);
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
            $rendered = $this->spreadComponentAttributesToChild($renderedChild, $renderedComponent);
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
                $fieldRootId,
                $checkboxGroupRootId,
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

    protected function spreadComponentAttributesToChild(string $childHtml, string $componentHtml): string
    {
        // Extract child tag + attributes
        if (!preg_match('/^\s*<([a-zA-Z0-9]+)([^>]*)>/', $childHtml, $childMatches)) {
            return $childHtml; // fallback
        }
        $childTag = $childMatches[1];
        $childAttrString = trim($childMatches[2]);

        // Parse child attributes into map
        preg_match_all(
            '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:="([^"]*)")?/',
            $childAttrString,
            $childAttrMatches,
            PREG_SET_ORDER,
        );
        $childAttrs = [];
        foreach ($childAttrMatches as $m) {
            $childAttrs[$m[1]] = $m[2] ?? null; // supports boolean attrs
        }

        // Extract parent/component attributes
        if (!preg_match('/^\s*<([a-zA-Z0-9]+)([^>]*)>/', $componentHtml, $compMatches)) {
            return $childHtml;
        }
        $compAttrString = trim($compMatches[2]);
        preg_match_all(
            '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:="([^"]*)")?/',
            $compAttrString,
            $compAttrMatches,
            PREG_SET_ORDER,
        );
        foreach ($compAttrMatches as $m) {
            $name = $m[1];
            $value = $m[2] ?? null; // supports boolean attrs
            if (!isset($childAttrs[$name])) {
                $childAttrs[$name] = $value;
            }
        }

        // Rebuild attributes
        $finalAttrs = '';
        foreach ($childAttrs as $k => $v) {
            $finalAttrs .= $v === null ? " {$k}" : ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }

        // Replace child opening tag
        return preg_replace('/^\s*<' . $childTag . '[^>]*>/', '<' . $childTag . $finalAttrs . '>', $childHtml, 1);
    }

    protected function componentSupportsField(string $baseName): bool
    {
        return in_array($baseName, Constants::COMPONENTS_THAT_SUPPORT_FIELD, true);
    }
}
