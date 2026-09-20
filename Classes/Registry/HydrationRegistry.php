<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Utility\EnumUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HydrationRegistry
{
    private const string SCRIPT_ID = 'fluid-primitives-hydration-data';

    /** @var array<string, array<string, mixed>> */
    private array $registry = [];
    private static ?self $instance = null;
    private array $globals = [];
    private bool $globalsResolved = false;

    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly HydrationScriptBuilder $scriptBuilder = new HydrationScriptBuilder(),
        private readonly NestedComponentRegistry $nestedComponentRegistry = new NestedComponentRegistry(),
    ) {}

    public static function getInstance(): self
    {
        if (!self::$instance instanceof \Jramke\FluidPrimitives\Registry\HydrationRegistry) {
            $container = GeneralUtility::getContainer();
            /** @var self $instance */
            $instance = $container->get(self::class);
            self::$instance = $instance;
        }
        return self::$instance;
    }

    public function add(string $componentType, string $id, array $props): void
    {
        if (($this->registry[$componentType] ?? null) === null) {
            $this->registry[$componentType] = [];
        }

        /** @var array<string, mixed> $normalizedProps */
        $normalizedProps = EnumUtility::normalize($props);
        $this->registry[$componentType][$id] = $normalizedProps;

        // Update the asset collector whenever data changes
        $this->updateAssetCollector();
    }

    public function get(string $componentType, string $id): ?array
    {
        // Not actually redundant - the assignment is what the @var narrows; inlining it into the
        // return statement would lose that annotation and bring back the mixed-return-statement error.
        // @mago-expect lint:inline-variable-return
        /** @var array<string, mixed>|null $props */
        $props = $this->registry[$componentType][$id] ?? null;
        return $props;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
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
        $nestedComponents = $this->nestedComponentRegistry->getNestedComponentsByScope();
        $js = $this->scriptBuilder->build($this->registry, $this->getGlobals(), $nestedComponents, $development);

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

        $this->globals['debug'] = $this->scriptBuilder->isDevelopment();

        $request = $this->getRequest();
        if (!$request instanceof ServerRequestInterface) {
            return;
        }

        // Narrowed immediately below via is_object()/method_exists() - a generic PSR-7 request
        // attribute has no narrower static type, and there's no Typed:: equivalent for objects.
        // @mago-expect analysis:mixed-assignment
        $language = $request->getAttribute('language');
        $locale = is_object($language) && method_exists($language, 'getLocale') ? (string)$language->getLocale() : '';

        $this->globals['locale'] = $locale;
    }

    private function getRequest(): ?ServerRequestInterface
    {
        // This registry is a hard singleton with no request-scoped construction path (see
        // getInstance()), so it has no other way to reach the current request than TYPO3's own
        // global - there is no DI-injectable alternative available here. Narrowed immediately below
        // via instanceof.
        // @mago-expect lint:no-global
        // @mago-expect analysis:mixed-assignment
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface ? $request : null;
    }
}
