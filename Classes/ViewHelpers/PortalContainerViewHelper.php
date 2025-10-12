<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Registry\PortalRegistry;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the elements used inside the `ui:portal` ViewHelper into the current position in the DOM.
 * 
 * You need to place at least one instance of this ViewHelper in your layout or page template to act as the target container for all portalled content.
 * 
 * ## Example
 * Place this in your main layout or page template, typically just before the closing `</body>` tag:
 * ```html
 * <f:layout name="Default" />
 * ...
 * <ui:portalContainer />
 * ```
 */
class PortalContainerViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'The name of the container', false, 'default');
    }

    public function render(): string
    {
        $portalledHtmlStrings = PortalRegistry::getAllByName($this->arguments['name']);

        if (empty($portalledHtmlStrings)) {
            return '';
        }

        $concatenatedHtml = implode("\n", array_map('trim', $portalledHtmlStrings));

        PortalRegistry::clearByName($this->arguments['name']);

        return $concatenatedHtml;
    }
}
