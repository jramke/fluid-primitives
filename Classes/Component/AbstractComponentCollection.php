<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Component;

use Jramke\FluidPrimitives\Annotations\AdditionalArgumentsAllowedAnnotation;
use Jramke\FluidPrimitives\Factory\ComponentRendererFactory;
use Jramke\FluidPrimitives\Utility\ComponentRootUtility;
use Jramke\FluidPrimitives\Utility\PropsUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Component\ComponentAdapter;
use TYPO3Fluid\Fluid\Core\Component\ComponentDefinition;
use TYPO3Fluid\Fluid\Core\Component\ComponentRendererInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;
use TYPO3Fluid\Fluid\Core\ViewHelper\UnresolvableViewHelperException;

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
     * Overwrite this method if you want to provide context classes for your components
     *
     * @return array<string>
     */
    public function getContextNamespaces(): array
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
        if (($this->componentDefinitionsCache[$viewHelperName] ?? null) === null) {
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

            $isDeclaredRoot = ComponentRootUtility::isDeclaredRootFromViewHelperName($viewHelperName, $this);
            $argumentDefinitions = $parsedTemplate->getArgumentDefinitions();

            // No reserved-prop collision check here: a template can only ever end up with a reserved
            // name in $argumentDefinitions by explicitly authoring `<ui:prop name="asChild">` (or
            // similar) itself, and PropViewHelper::nodeInitializedEvent() already rejects that at the
            // one place it could happen - checking again here, on the definitions ui:useProps may have
            // already merged in, used to incorrectly also reject asChild/class/rootId legitimately
            // *inherited* from an imported component.

            $templateString = $this->getTemplatePaths()->getTemplateSource('Default', $templateName);

            // asChild itself is opt-in now - a template registers it explicitly by using
            // {ui:asChild()} inline on whichever tag should receive the merged attributes (see
            // AsChildViewHelper::nodeInitializedEvent()), which already ran during the parse above
            // and already populated $argumentDefinitions, exactly like any ui:prop-declared or
            // ui:useProps-imported argument. Nothing to detect here.

            if ($isDeclaredRoot) {
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

                $argumentDefinitions['autoMount'] = new ArgumentDefinition(
                    'autoMount',
                    'boolean',
                    'Whether the client initializes the component on its own. Set it to false to mount it yourself with `mount()`',
                    false,
                    true,
                );
            }

            // only add the class argument if the template string uses it. A template that only
            // delegates via `ui:useProps` already has class merged in above when the component it
            // imports from supports it.
            if (preg_match('/(?<!\{)\{class\}(?!\})|(?<![A-Za-z0-9_-])class(?!\s*=|\s*\})/i', $templateString)) {
                $argumentDefinitions['class'] = new ArgumentDefinition(
                    'class',
                    'string',
                    'The CSS class(es) to be applied to the component.',
                    false,
                    null,
                );
            }

            // additionalArgumentsAllowed is fully structural now: AttributesViewHelper (for a
            // template's own ui:attributes() usage) and UsePropsViewHelper (for a genuine as=
            // delegation import, see its nodeInitializedEvent()) are the only two producers, both
            // attaching AdditionalArgumentsAllowedAnnotation to whichever real argument definition
            // is the reason - no text scanning, nothing to strip afterward.
            $additionalArgumentsAllowed = false;
            foreach ($argumentDefinitions as $argumentDefinition) {
                foreach ($argumentDefinition->getAnnotations() as $annotation) {
                    if (!$annotation instanceof AdditionalArgumentsAllowedAnnotation) {
                        continue;
                    }
                    $additionalArgumentsAllowed = true;
                    break 2;
                }
            }

            // Every component gets a spreadProps argument, the same way rootId/class do - not
            // conditionally patched in only when missing, since UsePropsViewHelper no longer ever
            // declares one itself (it binds the forwardable prop names under the author-chosen
            // `as=` name instead).
            $argumentDefinitions['spreadProps'] = PropsUtility::createSpreadPropsArgumentDefinition();

            $this->componentDefinitionsCache[$viewHelperName] = new ComponentDefinition(
                $viewHelperName,
                $argumentDefinitions,
                $additionalArgumentsAllowed,
                $parsedTemplate->getAvailableSlots(),
            );
        }
        return $this->componentDefinitionsCache[$viewHelperName];
    }

    /**
     * Whether $viewHelperName's own declared shape (folder-shape default included) is root - see
     * {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility::isDeclaredRootFromViewHelperName()}
     * for the underlying rule. A plain lookup on `getComponentDefinition()`'s own (already memoized)
     * result, not a second cache: `rootId` is only ever added to a root component's own argument
     * definitions, so its presence is exactly this fact.
     */
    final public function isDeclaredRoot(string $viewHelperName): bool
    {
        return array_key_exists('rootId', $this->getComponentDefinition($viewHelperName)->getArgumentDefinitions());
    }

    final public function getComponentRenderer(): ComponentRendererInterface
    {
        return GeneralUtility::makeInstance(ComponentRendererFactory::class)->create($this);
    }

    // $viewHelperName matches every other method on this class (resolveTemplateName,
    // getComponentDefinition, getAdditionalVariables, ...) rather than the parent
    // ViewHelperResolverDelegateInterface's generic $name - nothing calls this with named arguments
    // expecting Fluid's own parameter name.
    // @mago-expect analysis:incompatible-parameter-name
    final public function resolveViewHelperClassName(string $viewHelperName): string
    {
        $expectedTemplateName = $this->resolveTemplateName($viewHelperName);
        if (!$this->getTemplatePaths()->resolveTemplateFileForControllerAndActionAndFormat(
            'Default',
            $expectedTemplateName,
        )) {
            throw new UnresolvableViewHelperException(
                sprintf(
                    'Based on your spelling, the system would load the component template "%s.%s" in "%s", however this file does not exist.',
                    $expectedTemplateName,
                    $this->getTemplatePaths()->getFormat(),
                    implode(', ', $this->getTemplatePaths()->getTemplateRootPaths()),
                ),
                1748511297,
            );
        }
        return ComponentAdapter::class;
    }

    /**
     * @return class-string
     */
    final public function getNamespace(): string
    {
        return static::class;
    }
}
