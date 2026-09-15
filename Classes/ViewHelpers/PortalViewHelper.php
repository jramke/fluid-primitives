<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

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

        if (Typed::bool($this->arguments['disabled'])) {
            return $rendered;
        }

        if ($rendered === '') {
            return '';
        }

        PortalRegistry::getInstance()->add(Typed::string($this->arguments['name']), $rendered);
        return '';
    }
}
