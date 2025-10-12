<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HydrationRegistry
{
    private const SCRIPT_ID = 'fluid-primitives-hydration-data';

    private array $registry = [];
    private static ?self $instance = null;

    public function __construct(
        private readonly AssetCollector $assetCollector
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

        $this->registry[$componentType][$id] = $props;

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

    public function clear(): void
    {
        $this->registry = [];
    }

    private function updateAssetCollector(): void
    {
        if (empty($this->registry)) {
            return;
        }

        $isDevelopment = $this->isDevelopment();
        if ($isDevelopment) {
            $json = json_encode($this->registry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $json = json_encode($this->registry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $js = <<<JS
(function() {
window.FluidPrimitives = window.FluidPrimitives || {};
window.FluidPrimitives.uncontrolledInstances = {};
window.FluidPrimitives.hydrationData = $json;
})();
JS;

        $scriptAttributes = [
            'id' => self::SCRIPT_ID,
        ];

        if (!$isDevelopment) {
            $js = str_replace("\n", '', $js);
            $js = str_replace("\r", '', $js);
            $js = preg_replace('/\s+/', ' ', $js); // replace multiple whitespaces with one space
            $js = preg_replace('/\s*([{}();=])\s*/', '$1', $js); // remove spaces around special characters
            unset($scriptAttributes['id']);
        }

        // Add or update the script in AssetCollector
        $this->assetCollector->addInlineJavaScript(
            self::SCRIPT_ID,
            $js,
            $scriptAttributes,
            [
                'priority' => true
            ]
        );
    }

    private function isDevelopment(): bool
    {
        return Environment::getContext()->isDevelopment();
    }
}
