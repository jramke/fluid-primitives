<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Component\ComponentRenderer;
use Jramke\FluidPrimitives\Component\TemplateStructureViewHelperResolver;
use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\UnresolvableViewHelperException;
use TYPO3Fluid\Fluid\ViewHelpers\SlotViewHelper;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinition;
use TYPO3Fluid\Fluid\Core\Component\ComponentAdapter;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

abstract class AbstractComponentCollection implements ComponentCollectionInterface
{
    /**
     * Runtime cache for component definitions. This mainly speeds up uncached templates since we
     * create a new TemplateParser instance for each component to receive its argument definitions.
     *
     * @var array<string, ComponentDefinition>
     */
    private array $componentDefinitionsCache = [];

    /**
     * Overwrite this method if you want to provide additional variables to component views
     *
     * @param string $viewHelperName  ViewHelper tag name from a template, e. g. atom.button
     * @return array<string, mixed>
     */
    public function getAdditionalVariables(string $viewHelperName): array
    {
        return [];
    }

    /**
     * Resolve the component template name based on the ViewHelper tag name.
     *
     * @param string $viewHelperName  ViewHelper tag name from a template, e. g. atom.button
     * @return string                 Component template name to be used for this ViewHelper,
     *                                without format suffix, e. g. Atom/Button/Button
     */
    final public function resolveTemplateName(string $viewHelperName): string
    {
        $fragments = array_map(ucfirst(...), explode('.', $viewHelperName));
        $componentName = array_pop($fragments);
        $baseName = count($fragments) > 0 ? array_pop($fragments) : $componentName;
        $path = implode('/', $fragments);
        return ($path !== '' ? $path . '/' : '') . $baseName . '/' . $componentName;
    }

    /**
     * Fetches the component definition (arguments, slots) for a ViewHelper call by
     * parsing the underlying Fluid template
     *
     * @todo we might introduce a separate exception here and catch internal exceptions,
     *       e. g. if invalid template is supplied
     */
    final public function getComponentDefinition(string $viewHelperName): ComponentDefinition
    {
        if (!isset($this->componentDefinitionsCache[$viewHelperName])) {
            $templateName = $this->resolveTemplateName($viewHelperName);
            $renderingContext = new RenderingContext();
            // At this stage, the component template needs to be parsed to gather the component's definition,
            // such as argument definitions and available slots. Ideally, this is done without any additional state
            // present, so with an "empty" RenderingContext. Due to the current state of the TemplateParser,
            // we currently have several bad alternatives, of which only one (4.) really works:
            // 1. Suppress exceptions during parsing, e. g. for undefined ViewHelpers by enabling the
            //    TolerantErrorHandler. This currently doesn't work because exceptions with closing ViewHelper
            //    tags aren't intercepted properly by the parser and bubble up, which results in an invalid
            //    parsed template.
            // 2. Suppress execution of all third-party ViewHelpers by removing the NamespaceDetectionTemplateProcessor
            //    (so that no namespaces can be added in the template) and defining all namespaces that aren't "f" as
            //    ignored (to prevent parser exceptions): $viewHelperResolver->addNamespace('*', null).
            //    This currently doesn't work because TYPO3 extends the "f" namespace, so we would need to partially
            //    ignore "f" as well, which is not possible with the current API. In TYPO3 context, again this leads to
            //    unresolvable ViewHelper exceptions which we can't intercept because 1.
            // 3. Pass the ViewHelperResolver from the current renderingContext to the method, along with its
            //    state (global namespaces) and special handling of ViewHelpers (possible DI implementations). This
            //    would pollute the interface with a seemingly irrelevant dependency. It also has the disadvantage
            //    that _all_ ViewHelper calls within the template would be resolved, including other components, which
            //    can lead to a chain of component templates being parsed. On top of that, it simply doesn't work
            //    for recursive component calls (infinite regress for recursive component definition).
            // 4. Use a custom ViewHelperResolver that only resolves select ViewHelpers necessary for the template
            //    structure and short-circuits all other ViewHelper calls.
            // Option 4 is currently the least intrusive variant and is implemented in TemplateStructureViewHelperResolver.
            // @todo the TemplateParser should be able to analyze the template structure in a first parsing pass,
            //       without resolving all other ViewHelpers in a template (with the described consequences).
            $templateStructureResolver = new TemplateStructureViewHelperResolver();
            $templateStructureResolver->addNamespace('ui', 'Jramke\\FluidPrimitives\\ViewHelpers');
            $renderingContext->setViewHelperResolver($templateStructureResolver);
            $parsedTemplate = $renderingContext->getTemplateParser()->parse(
                $this->getTemplatePaths()->getTemplateSource('Default', $templateName),
                $this->getTemplatePaths()->getTemplateIdentifier('Default', $templateName),
            );

            $isRootComponent = ComponentUtility::isRootComponent($viewHelperName);
            $argumentDefinitions = $parsedTemplate->getArgumentDefinitions();

            foreach ($argumentDefinitions as $name => $definition) {
                if (in_array($name, Constants::RESERVED_PROPS, true)) {
                    throw new UnresolvableViewHelperException(sprintf(
                        'The argument "%s" is reserved and cannot be used as an argument inside component "%s".',
                        $name,
                        $viewHelperName,
                    ), 1748511298);
                }
            }

            $argumentDefinitions['asChild'] = new ArgumentDefinition(
                'asChild',
                'boolean',
                'If true the component uses its child only without the component template. Like Radix UI asChild or Base UI render props.',
                false,
                false,
            );

            $argumentDefinitions['class'] = new ArgumentDefinition(
                'class',
                'string',
                'The CSS class(es) to be applied to the component.',
                false,
                null,
            );

            if ($isRootComponent) {
                $argumentDefinitions['rootId'] = new ArgumentDefinition(
                    'rootId',
                    'string',
                    'The root ID of the component, used for hydration and identification.',
                    false,
                    null,
                );

                $argumentDefinitions['ids'] = new ArgumentDefinition(
                    'ids',
                    'array',
                    'The IDs of of the component parts for composition.',
                    false,
                    [],
                );

                $argumentDefinitions['controlled'] = new ArgumentDefinition(
                    'controlled',
                    'boolean',
                    'If true, the component is meant to be initialized manually inside another component',
                    false,
                    false,
                );
            }

            $templateString = $this->getTemplatePaths()->getTemplateSource('Default', $templateName);

            $additionalArgumentsAllowed = false;
            if (str_contains($templateString, 'ui:attributes(')) {
                // if the user used the ui:attributes viewhelper in the component template,
                // we want to allow tag attributes (additionalArguments) for this component
                $additionalArgumentsAllowed = true;

                $argumentDefinitions['attributes'] = new ArgumentDefinition(
                    'attributes',
                    'array',
                    'Additional attributes that should be rendered on the component where ui:attributes is used.',
                    false,
                    [],
                );
            }

            // for now we just allow additional arguments if a primitive is used with the spreadProps pattern
            // because all primitives support additional arguments/attributes
            if (str_contains($templateString, 'spreadProps') && str_contains($templateString, '<ui:useProps name="primitives:')) {
                $additionalArgumentsAllowed = true;
            }

            // If the ui:spreadProps viewhelper did not already initialized the spreadProps
            // with an array of the keys as default value, declare it here
            if (!array_key_exists('spreadProps', $argumentDefinitions)) {
                $argumentDefinitions['spreadProps'] = PropsUtility::createSpreadPropsArgumentDefinition();
            }

            $this->componentDefinitionsCache[$viewHelperName] = new ComponentDefinition(
                $viewHelperName,
                $argumentDefinitions,
                $additionalArgumentsAllowed,
                $parsedTemplate->getAvailableSlots(),
            );
        }
        return $this->componentDefinitionsCache[$viewHelperName];
    }

    final public function getComponentRenderer(): ComponentRendererInterface
    {
        return new ComponentRenderer($this);
    }

    final public function resolveViewHelperClassName(string $viewHelperName): string
    {
        $expectedTemplateName = $this->resolveTemplateName($viewHelperName);
        if (!$this->getTemplatePaths()->resolveTemplateFileForControllerAndActionAndFormat('Default', $expectedTemplateName)) {
            throw new UnresolvableViewHelperException(sprintf(
                'Based on your spelling, the system would load the component template "%s.%s" in "%s", however this file does not exist.',
                $expectedTemplateName,
                $this->getTemplatePaths()->getFormat(),
                implode(', ', $this->getTemplatePaths()->getTemplateRootPaths()),
            ), 1748511297);
        }
        return ComponentAdapter::class;
    }

    final public function getNamespace(): string
    {
        return static::class;
    }
}
