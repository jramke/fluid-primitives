<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Renders its content at the end of `<body>` instead of where you write it, for a tooltip, a dialog or anything else that has to leave its parent container for styling or positioning.
 *
 * The content part of a tooltip portals its positioner:
 *
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
 *
 * To render the content somewhere else, give the portal a `name` and place a matching [ui:portalContainer](./portalContainer). With `disabled="{true}"` the content stays in place.
 *
 * Inside a [`ui:template`](./template) the content stays in place too, wrapped in `<div data-portal>`, and moves to its destination when the client clones the template.
 *
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
        // instead keeps it part of the stencil's own clonable content. The wrapper is how the client finds
        // it again: a clone of the stencil moves it to where the portal wants to go (`movePortals()` in
        // Client/src/lib/portal.ts). A `disabled` portal stays in place for good, without a wrapper.
        if (Typed::bool($this->arguments['disabled'])) {
            return $rendered;
        }

        if ($rendered !== '' && NestedComponentRegistry::getInstance()->isTrackingScopeOpen()) {
            return sprintf(
                '<div data-portal="%s" style="display: contents">%s</div>',
                htmlspecialchars(Typed::string($this->arguments['name'])),
                $rendered,
            );
        }

        if ($rendered === '') {
            return '';
        }

        PortalRegistry::getInstance()->add(Typed::string($this->arguments['name']), $rendered);
        return '';
    }
}
