<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class PortalRegistry
{
    public const string DEFAULT_NAME = 'default';

    /** @var array<string, string[]> */
    private array $registry = [];
    private static ?self $instance = null;

    public function __construct(
        private readonly PageRenderer $pageRenderer,
    ) {}

    public static function getInstance(): self
    {
        if (!self::$instance instanceof \Jramke\FluidPrimitives\Registry\PortalRegistry) {
            $container = GeneralUtility::getContainer();
            /** @var self $instance */
            $instance = $container->get(self::class);
            self::$instance = $instance;
        }
        return self::$instance;
    }

    public function add(string $name, string $html): void
    {
        $this->registry[$name][] = $html;

        // The default bucket needs no matching ui:portalContainer placement in a full TYPO3 page
        // render - it's pushed straight into PageRenderer's footer, which core renders at the end of
        // <body> on its own. Rendering pipelines that bypass PageRenderer entirely (e.g. an isolated
        // component render for a Storybook preview) never consume this, so an explicit
        // ui:portalContainer for "default" is still honored there via the registry below.
        if ($name === self::DEFAULT_NAME) {
            $this->pageRenderer->addFooterData($html);
        }
    }

    /**
     * @return array<string, string[]>
     */
    public function getAll(): array
    {
        return $this->registry;
    }

    /**
     * @return string[]
     */
    public function getAllByName(string $name): array
    {
        return $this->registry[$name] ?? [];
    }

    public function clearByName(string $name): void
    {
        unset($this->registry[$name]);
    }

    public function clearAll(): void
    {
        $this->registry = [];
    }
}
