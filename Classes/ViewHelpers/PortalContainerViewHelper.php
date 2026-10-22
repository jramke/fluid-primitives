<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Registry\PortalRegistry;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Renders the content that [`ui:portal`](./portal) collected under the same `name` at the place where you write it.
 *
 * Use it to portal content somewhere other than the end of `<body>`. Give the portal a custom `name` and place the container with that name where the content should end up:
 *
 * ```html
 * <ui:portal name="sidebar">...</ui:portal>
 * ...
 * <ui:portalContainer name="sidebar" />
 * ```
 *
 * The default `name` renders at the end of `<body>` through TYPO3's page rendering. Add `<ui:portalContainer />` for it when you render outside that pipeline, for example in an isolated component preview.
 *
 * The container also renders an empty `<template data-portal-container>` marker. The portals of a cloned [`ui:template`](./template) move after it.
 *
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
