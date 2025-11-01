<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Domain\Model\TagAttributes;
use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\ViewHelpers\ContextViewHelper;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;
use TYPO3Fluid\Fluid\View\TemplateView;
use TYPO3Fluid\Fluid\ViewHelpers\SlotViewHelper;
use TYPO3Fluid\Fluid\Core\Variables\VariableProviderInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentProcessorInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\StrictArgumentProcessor;

final readonly class ComponentRenderer implements ComponentRendererInterface
{
    public function __construct(private ComponentCollectionInterface $componentResolver) {}

    /**
     * Renders a Fluid template to be used as a component. The necessary view configuration (template paths,
     * template name and possible additional variables) are expected to be provided by the component template
     * resolver.
     *
     * @param array<string, mixed> $arguments
     * @param array<string, \Closure> $slots
     */
    public function renderComponent(string $viewHelperName, array $arguments, array $slots, RenderingContextInterface $parentRenderingContext): string
    {
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

        if (!isset($arguments['rootId'])) {
            if ($isRootComponent) {
                $arguments['rootId'] = ComponentUtility::uid();
            } else {
                $arguments['rootId'] = $renderingContext->getVariableProvider()->get('rootId') ?? null;
            }
        }

        $argumentDefinitions = $this->componentResolver->getComponentDefinition($viewHelperName)->getArgumentDefinitions();

        // Extract props marked for client from internal argument definition
        $propsMarkedForClient = [];
        $propsMarkedForClientDefinition = $argumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY] ?? null;
        if ($propsMarkedForClientDefinition) {
            $propsMarkedForClient = $propsMarkedForClientDefinition->getDefaultValue() ?? [];
        }
        unset($argumentDefinitions[Constants::PROPS_MARKED_FOR_CLIENT_KEY]);
        unset($arguments[Constants::PROPS_MARKED_FOR_CLIENT_KEY]);

        // Extract props marked for context from internal argument definition
        $propsMarkedForContext = [];
        $propsMarkedForContextDefinition = $argumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY] ?? null;
        if ($propsMarkedForContextDefinition) {
            $propsMarkedForContext = $propsMarkedForContextDefinition->getDefaultValue() ?? [];
        }
        unset($argumentDefinitions[Constants::PROPS_MARKED_FOR_CONTEXT_KEY]);
        unset($arguments[Constants::PROPS_MARKED_FOR_CONTEXT_KEY]);

        $definedArgumentKeys = array_keys($argumentDefinitions);
        $additionalArguments = array_diff_key($arguments, array_flip($definedArgumentKeys));

        // We remove the additional arguments here
        // They are added later again coupled to an internal variable so they can be used by the ui:attributes view helper
        foreach ($additionalArguments as $key => $_) {
            unset($arguments[$key]);
        }

        // Add spreaded props as arguments
        if (isset($arguments['spreadProps']) && $arguments['spreadProps']) {
            $propsToUse = $parentRenderingContext->getVariableProvider()->get('spreadProps') ?? [];
            if (!empty($propsToUse) && is_array($propsToUse)) {
                foreach ($propsToUse as $propToUse) {
                    if ($propToUse === 'attributes') {
                        // here we can simply grab the TagAttributes object as it already has resolved the additional attributes and the ones from the attributes argument
                        $spreadTagAttributes = $parentRenderingContext->getVariableProvider()->get(Constants::TAG_ATTRIBUTES_KEY) ?? null;
                        if (empty($spreadTagAttributes)) {
                            $propValue = [];
                        } else {
                            $propValue = $spreadTagAttributes->renderAsArray();
                        }
                    } else {
                        $propValue = $arguments[$propToUse] ?? $parentRenderingContext->getVariableProvider()->get($propToUse) ?? $renderingContext->getVariableProvider()->get($propToUse) ?? null;
                    }
                    $arguments[$propToUse] = $propValue;
                }
            }
        }

        $renderingContext->setVariableProvider($renderingContext->getVariableProvider()->getScopeCopy($arguments));

        // Provide slots to SlotViewHelper
        $renderingContext->setViewHelperVariableContainer(new ViewHelperVariableContainer());
        $renderingContext->getViewHelperVariableContainer()->addAll(SlotViewHelper::class, $slots);

        // Create Fluid view for component
        $view = new TemplateView($renderingContext);

        $view->assign('rootId', $arguments['rootId'] ?? null);

        $view->getRenderingContext()->getVariableProvider()->remove('settings');
        $view->assign('settings', ComponentUtility::getSettings());

        $baseName = ComponentUtility::getComponentBaseNameFromViewHelperName($viewHelperName);

        $componentData = [
            'fullName' => $viewHelperName,
            'baseName' => $baseName,
            'isRoot' => $isRootComponent,
            'isComposable' => $isComposableComponent
        ];
        $view->assign('component', $componentData);

        // Pick up potential context from parent component
        if (!$isRootComponent) {
            $ctx = $this->getRootComponentContext($parentRenderingContext, $baseName);
            if ($ctx) $view->assign('context', $ctx);
        }

        $otherComponentContexts = $this->getOtherComponentContexts($parentRenderingContext, $baseName);
        $renderingContext->getViewHelperVariableContainer()->addAll(ContextViewHelper::class, $otherComponentContexts);

        // Expose additional arguments as as tag attributes so they can be used by the ui:attributes view helper
        $hasAdditionalAttributes = false;
        $attributesArgument = $arguments['attributes'] ?? null;
        if (count($additionalArguments) > 0) $hasAdditionalAttributes = true;
        if (isset($arguments['attributes'])) {
            if (is_string($arguments['attributes']) && trim($arguments['attributes']) !== '') {
                $hasAdditionalAttributes = true;
            } elseif (is_array($arguments['attributes']) && count($arguments['attributes']) > 0) {
                $hasAdditionalAttributes = true;
            }
        }
        if ($hasAdditionalAttributes) {
            $attributes = is_array($attributesArgument) ? $attributesArgument : TagAttributes::stringToArray($attributesArgument ?? '');
            $mergedAttributes = array_merge(
                $additionalArguments,
                $attributes
            );
            $view->assign(Constants::TAG_ATTRIBUTES_KEY, new TagAttributes($mergedAttributes));
        }

        // render() call includes validation of provided arguments
        $view->assignMultiple($this->componentResolver->getAdditionalVariables($viewHelperName));

        if ($isComposableComponent) {
            // Expose variables as context so it can be picked up in other components rendered inside this component.
            if ($isRootComponent) {
                $contextVariables = $this->buildContextVariables($argumentDefinitions, $view->getRenderingContext()->getVariableProvider(), new StrictArgumentProcessor());
                $contextClassName = ComponentUtility::getContextClassNameFromViewHelperName($viewHelperName);
                $context = new $contextClassName($renderingContext, $contextVariables);
                $parentRenderingContext->getVariableProvider()->add("__context_{$baseName}", $context);
            }

            if (!empty($propsMarkedForContext) && !$isRootComponent) {
                $propsMarkedForContextValues = [];
                foreach ($propsMarkedForContext as $name => $_) {
                    if (isset($arguments[$name]) || isset($argumentDefinitions[$name])) {
                        $propsMarkedForContextValues[$name] = $arguments[$name] ?? $argumentDefinitions[$name]->getDefaultValue() ?? null;
                    }
                }
                $context = $parentRenderingContext->getVariableProvider()->get("__context_{$baseName}");
                if ($context) {
                    $context->set(ComponentUtility::getSubcomponentNameFromViewHelperName($viewHelperName), $propsMarkedForContextValues);
                }
            }
        }

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
            $parentRenderingContext->getVariableProvider()->remove("__context_{$baseName}");

            $rootId = ComponentUtility::getRootIdFromContext($renderingContext);
            if (!$rootId) {
                throw new \RuntimeException('No rootId found for root component ' . $viewHelperName . '.', 1756025241);
            }

            // only register the components props for hydration if the user used the ui:ref viewhelper
            if (preg_match('/data-hydrate-[^=]*="' . preg_quote($rootId, '/') . '"/', $rendered)) {

                $propsMarkedForClientValues = [];
                foreach ($propsMarkedForClient as $name => $_) {
                    if (isset($arguments[$name]) || isset($argumentDefinitions[$name])) {
                        $propsMarkedForClientValues[$name] = $arguments[$name] ?? $argumentDefinitions[$name]->getDefaultValue() ?? null;
                    }
                }

                // we dont want to send null values to the client, defaults should be defined in the component ts file
                $propsMarkedForClientValues = array_filter($propsMarkedForClientValues, function ($value) {
                    return !is_null($value);
                });

                $props = [...$propsMarkedForClientValues];
                unset($props['id']); // Remove potential id from client props as it is handled separately
                unset($props['ids']);

                $registry = HydrationRegistry::getInstance();
                $registry->add(
                    $baseName,
                    $rootId,
                    [

                        'controlled' => $arguments['controlled'] ?? false,
                        'props' => [
                            'id' => $rootId,
                            'ids' => $arguments['ids'] ?? [],
                            ...$props,
                        ],
                    ]
                );
            }
        }

        return $rendered;
    }

    // This is somewhat what is already done by the template view when we call the render method but we need the variables earlier so we can expose them to the context.
    // We also dont throw anything here as the validation is handled by the mentioned render method.
    protected function buildContextVariables(
        array $argumentDefinitions,
        VariableProviderInterface $variableProvider,
        ArgumentProcessorInterface $argumentProcessor,
    ): array {
        $variablesToRemove = [
            'component',
            'settings',
            'context',
            'class',
            'asChild',
            'asChildData',
            '__tagAttributes'
        ];

        $contextVariables = $variableProvider->getAll();

        foreach ($argumentDefinitions as $argumentDefinition) {
            $argumentName = $argumentDefinition->getName();
            if ($variableProvider->exists($argumentName)) {
                $processedValue = $argumentProcessor->process($variableProvider->get($argumentName), $argumentDefinition);
                if (!$argumentProcessor->isValid($processedValue, $argumentDefinition)) {
                    continue; // Skip invalid values
                }
                $contextVariables[$argumentName] = $processedValue;
            } elseif ($argumentDefinition->isRequired()) {
                continue; // Skip required arguments that are not provided
            } else {
                $contextVariables[$argumentName] = $argumentDefinition->getDefaultValue();
            }
        }

        foreach ($variablesToRemove as $var) {
            unset($contextVariables[$var]);
        }

        return $contextVariables;
    }

    protected function getOtherComponentContexts(RenderingContextInterface $parentRenderingContext, string $baseName): array
    {
        $contexts = [];

        $allVars = $parentRenderingContext->getVariableProvider()->getAll();
        foreach ($allVars as $key => $value) {
            if (str_starts_with($key, '__context_')) {
                if ($key === "__context_{$baseName}") continue;
                $contexts[substr($key, 10)] = $value; // Remove the __context_ prefix
            }
        }

        return $contexts;
    }

    protected function getRootComponentContext(RenderingContextInterface $renderingContext, string $baseName): AbstractComponentContext|null
    {
        $variableProvider = $renderingContext->getVariableProvider();
        $ctx = $variableProvider->get("__context_{$baseName}") ?? null;
        if ($ctx === null && $variableProvider->getByPath('component.baseName') === $baseName) {
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
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:="([^"]*)")?/', $childAttrString, $childAttrMatches, PREG_SET_ORDER);
        $childAttrs = [];
        foreach ($childAttrMatches as $m) {
            $childAttrs[$m[1]] = $m[2] ?? null; // supports boolean attrs
        }

        // Extract parent/component attributes
        if (!preg_match('/^\s*<([a-zA-Z0-9]+)([^>]*)>/', $componentHtml, $compMatches)) {
            return $childHtml;
        }
        $compAttrString = trim($compMatches[2]);
        preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?:="([^"]*)")?/', $compAttrString, $compAttrMatches, PREG_SET_ORDER);
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
            $finalAttrs .= $v === null ? " $k" : ' ' . $k . '="' . htmlspecialchars($v, ENT_QUOTES) . '"';
        }

        // Replace child opening tag
        $finalHtml = preg_replace(
            '/^\s*<' . $childTag . '[^>]*>/',
            '<' . $childTag . $finalAttrs . '>',
            $childHtml,
            1
        );

        return $finalHtml;
    }
}
