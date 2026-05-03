<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Utility\EnumUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\Environment;
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
    ) {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
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
        if (empty($this->registry)) {
            return;
        }

        $globals = $this->getGlobals();

        $js = <<<JS
        (function() {
        window.FluidPrimitives = {
            uncontrolledInstances: {},
            globals: {$this->toJson($globals)},
            hydrationData: {$this->toJson($this->registry)}
        };
        })();
        JS;

        $scriptAttributes = [
            'id' => self::SCRIPT_ID,
        ];

        if (!$this->isDevelopment()) {
            $js = str_replace("\n", '', $js);
            $js = str_replace("\r", '', $js);
            $js = preg_replace('/\s+/', ' ', $js); // replace multiple whitespaces with one space
            $js = preg_replace('/\s*([{}();=])\s*/', '$1', $js); // remove spaces around special characters
            unset($scriptAttributes['id']);
        }

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

        if (!$request) {
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
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface ? $request : null;
    }

    private function isDevelopment(): bool
    {
        try {
            return Environment::getContext()->isDevelopment();
        } catch (\Throwable) {
            // If Environment is not initialized (e.g., in unit tests), assume production
            return false;
        }
    }

    private function toJson(array $data): string
    {
        if ($this->isDevelopment()) {
            return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
