<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Renders the elements used inside the `ui:portal` ViewHelper into the current position in the DOM.
 *
 * The default `name` bucket is rendered automatically at the end of `<body>` (via TYPO3's `PageRenderer`),
 * so this ViewHelper is no longer needed for it in a normal page render. It's still useful for two cases:
 * placing portalled content somewhere other than the end of `<body>` (give `ui:portal` a matching custom
 * `name`), or rendering outside TYPO3's regular page pipeline (e.g. an isolated component preview), where
 * nothing else flushes the registry for you.
 *
 * It renders an empty `<template data-portal-container>` marker first: portals inside a `ui:template` stencil
 * are moved after it when the client clones the stencil.
 *
 * ## Example
 * Place a matching `name` wherever you want that portal's content to end up:
 * ```html
 * <ui:portal name="sidebar">...</ui:portal>
 * ...
 * <ui:portalContainer name="sidebar" />
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
        $name = Typed::string($this->arguments['name']);
        // where the client moves the portals of a cloned stencil to
        $marker = sprintf('<template data-portal-container="%s"></template>', htmlspecialchars($name));
        $portalledHtmlStrings = PortalRegistry::getInstance()->getAllByName($name);

        if ($portalledHtmlStrings === []) {
            return $marker;
        }

        $concatenatedHtml = implode("\n", array_map(trim(...), $portalledHtmlStrings));

        PortalRegistry::getInstance()->clearByName($name);

        return $marker . $concatenatedHtml;
    }
}
