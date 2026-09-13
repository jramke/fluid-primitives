<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Utility\EnumUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HydrationRegistry
{
    private const SCRIPT_ID = 'fluid-primitives-hydration-data';

    private array $registry = [];
    private static ?self $instance = null;
    private array $globals = [];
    private bool $globalsResolved = false;

    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly HydrationScriptBuilder $scriptBuilder = new HydrationScriptBuilder(),
    ) {}

    public static function getInstance(): self
    {
        if (!self::$instance instanceof \Jramke\FluidPrimitives\Registry\HydrationRegistry) {
            $container = GeneralUtility::getContainer();
            self::$instance = $container->get(self::class);
        }
        return self::$instance;
    }

    public function add(string $componentType, string $id, array $props): void
    {
        if (!isset($this->registry[$componentType])) {
            $this->registry[$componentType] = [];
        }

        $this->registry[$componentType][$id] = EnumUtility::normalize($props);

        // Update the asset collector whenever data changes
        $this->updateAssetCollector();
    }

    public function get(string $componentType, string $id): ?array
    {
        return $this->registry[$componentType][$id] ?? null;
    }

    public function getAll(): array
    {
        return $this->registry;
    }

    public function getGlobals(): array
    {
        $this->resolveGlobals();
        return $this->globals;
    }

    public function clear(): void
    {
        $this->registry = [];
        $this->globals = [];
        $this->globalsResolved = false;
    }

    private function updateAssetCollector(): void
    {
        if ($this->registry === []) {
            return;
        }

        $development = $this->scriptBuilder->isDevelopment();
        $js = $this->scriptBuilder->build($this->registry, $this->getGlobals(), $development);

        $scriptAttributes = $development ? ['id' => self::SCRIPT_ID] : [];

        // Add or update the script in AssetCollector
        $this->assetCollector->addInlineJavaScript(self::SCRIPT_ID, $js, $scriptAttributes, [
            'priority' => true,
        ]);
    }

    private function resolveGlobals(): void
    {
        if ($this->globalsResolved) {
            return;
        }

        $this->globalsResolved = true;
        $request = $this->getRequest();

        if (!$request instanceof ServerRequestInterface) {
            return;
        }

        $language = $request->getAttribute('language');
        $locale = is_object($language) && method_exists($language, 'getLocale') ? (string)$language->getLocale() : '';

        $this->globals = [
            'locale' => $locale,
        ];
    }

    private function getRequest(): ?ServerRequestInterface
    {
        // This registry is a hard singleton with no request-scoped construction path (see
        // getInstance()), so it has no other way to reach the current request than TYPO3's own
        // global - there is no DI-injectable alternative available here.
        // @mago-expect lint:no-global
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface ? $request : null;
    }
}
