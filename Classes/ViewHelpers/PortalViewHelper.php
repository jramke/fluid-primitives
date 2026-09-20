<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Contexts\ComponentContextInterface;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * This ViewHelper allows you to render content in a different part of the DOM tree than where it is defined.
 * This is particularly useful for modals, tooltips, or any component that needs to break out of its parent container for styling or positioning reasons.
 *
 * With the default `name`, portalled content is rendered automatically at the end of `<body>` - no further setup needed.
 * Pass a custom `name` together with a matching [ui:portalContainer](./portalContainer) ViewHelper if you want portalled content to end up somewhere else instead.
 *
 * ## Example
 * Common use case inside `Tooltip/Content.html`:
 * ```html
 * <ui:portal>
 *     <primitives:tooltip.positioner>
 *         <primitives:tooltip.content>
 *             <primitives:tooltip.arrow />
 *             <f:slot />
 *         </primitives:tooltip.content>
 *     </primitives:tooltip.positioner>
 * </ui:portal>
 * ```
 */
class PortalViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'The name of the target container', false, 'default');
        $this->registerArgument(
            'disabled',
            'bool',
            'If set to true, the portal functionality is disabled and content is rendered in place',
            false,
            false,
        );
    }

    public function render(): string
    {
        $rendered = trim((string)$this->renderChildren());

        // A ui:template stencil is inert HTML sitting inside a <template> tag - never shown, only
        // cloned client-side once real data exists. PortalRegistry::add() (below) buffers content
        // into the page's *live* HTML unconditionally, with no awareness of that - so portalling
        // something authored inside a stencil (e.g. a Popover nested in a FieldArray row's
        // itemTemplate) would leak it into the live page immediately on load, before any row even
        // exists, and with placeholder ids that were never meant to be shown. Rendering in place
        // instead keeps it part of the stencil's own clonable content, exactly like `disabled`
        // already does for a consumer who opts out of portalling by hand - `context.isRenderStencil`
        // is `ui:template`'s own marker for "opt out of it for me automatically" (see
        // TemplateViewHelper's own docblock), not something a template author needs to know to ask
        // for explicitly.
        if (Typed::bool($this->arguments['disabled']) || $this->isRenderingInsideAStencil()) {
            return $rendered;
        }

        if ($rendered === '') {
            return '';
        }

        PortalRegistry::getInstance()->add(Typed::string($this->arguments['name']), $rendered);
        return '';
    }

    private function isRenderingInsideAStencil(): bool
    {
        $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
            'Portal ViewHelper is missing its rendering context.',
            1_789_900_001,
        );
        $variableProvider = $renderingContext->getVariableProvider();

        // Narrowed immediately below via instanceof - there's no Typed:: equivalent for objects.
        // @mago-expect analysis:mixed-assignment
        $context = $variableProvider->exists('context') ? $variableProvider->get('context') : null;

        return $context instanceof ComponentContextInterface && Typed::bool($context->get('isRenderStencil'));
    }
}
