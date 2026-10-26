<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Jramke\FluidPrimitives\Contexts\GlobalContextInterface;
use Jramke\FluidPrimitives\Domain\Dto\HydrationEntry;
use Jramke\FluidPrimitives\Exception\HydrationException;
use Jramke\FluidPrimitives\Utility\ClientPropsContextExtractor;
use Jramke\FluidPrimitives\Utility\EnumUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

// The globals accessors and their request handling are one concern of this per-request singleton.
class HydrationRegistry
{
    private const string SCRIPT_ID = 'fluid-primitives-hydration-data';

    /** @var array<string, mixed> */
    private array $registry = [];
    private static ?self $instance = null;
    private array $globals = [];
    private bool $globalsResolved = false;
    private bool $globalsRequested = false;

    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly GlobalContextInterface $globalContext,
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

    /**
     * `$namespace` is the Fluid namespace identifier (e.g. "ui"/"primitives") the collection that
     * rendered this component is registered under - see
     * {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::$namespaceIdentifier}. Two
     * collections can legitimately register a same-named `$componentType` (e.g. a styled `ui`
     * wrapper around a `primitives` component it doesn't expose every prop of) - nesting by
     * namespace first keeps those genuinely separate instead of one silently overwriting the other.
     *
     * `$componentType` (see {@see \Jramke\FluidPrimitives\Utility\ComponentNameUtility::getComponentBaseNameFromViewHelperName()})
     * can itself be a dot-joined path (e.g. "molecules.checkboxGroup") - each segment becomes its
     * own nested level here, mirroring the client's own `getHydrationData()` walk, so a tiered or
     * otherwise multi-segment identity gets its own distinct branch of the tree rather than
     * colliding with an unrelated component sharing only its first segment.
     */
    public function add(string $namespace, string $componentType, HydrationEntry $entry): void
    {
        /** @var array<string, mixed> $normalizedProps */
        $normalizedProps = EnumUtility::normalize($entry->toArray());

        $segments = explode('.', $componentType);
        $lastSegment = array_pop($segments);

        $this->registry[$namespace] ??= [];
        /** @var array<string, mixed> $bucket */
        $bucket = &$this->registry[$namespace];
        foreach ($segments as $segment) {
            $bucket[$segment] ??= [];
            /** @var array<string, mixed> $bucket */
            $bucket = &$bucket[$segment];
        }

        $bucket[$lastSegment] ??= [];
        /** @var array<string, array<string, mixed>> $leaf */
        $leaf = &$bucket[$lastSegment];
        if (array_key_exists($entry->id, $leaf)) {
            throw new HydrationException(
                sprintf('The rootId "%s" is already used by another <%s> component.', $entry->id, $componentType),
                1_788_200_003,
            );
        }
        $leaf[$entry->id] = $normalizedProps;

        // Update the asset collector whenever data changes
        $this->updateAssetCollector();
    }

    public function get(string $namespace, string $componentType, string $id): ?array
    {
        /** @var array<string, mixed>|null $bucket */
        $bucket = $this->registry[$namespace] ?? null;
        foreach (explode('.', $componentType) as $segment) {
            if (!is_array($bucket)) {
                return null;
            }
            /** @var array<string, mixed>|null $bucket */
            $bucket = $bucket[$segment] ?? null;
        }

        if (!is_array($bucket)) {
            return null;
        }

        // Not actually redundant - the assignment is what the @var narrows; inlining it into the
        // return statement would lose that annotation and bring back the mixed-return-statement error.
        // @mago-expect lint:inline-variable-return
        /** @var array<string, mixed>|null $props */
        $props = $bucket[$id] ?? null;
        return $props;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return $this->registry;
    }

    /**
     * Makes the inline script carry the globals even when no component hydrates on this page, because
     * a template read one: the client then finds them with `getGlobal()`. Called by the first
     * `getGlobals()` of a component context per request.
     */
    public function requestGlobals(): void
    {
        $this->globalsRequested = true;
        $this->updateAssetCollector();
    }

    /**
     * Gives the global context the request of the render that registers a component, before its values
     * are read. The first request wins, see {@see GlobalContextInterface::initialize()}.
     */
    public function initializeGlobals(RenderingContextInterface $renderingContext): void
    {
        if (!$renderingContext->hasAttribute(ServerRequestInterface::class)) {
            return;
        }

        $this->globalContext->initialize($renderingContext->getAttribute(ServerRequestInterface::class));
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
        $this->globalsRequested = false;
    }

    private function updateAssetCollector(): void
    {
        if ($this->registry === [] && !$this->globalsRequested) {
            return;
        }

        $development = $this->scriptBuilder->isDevelopment();
        $nestedComponents = $this->nestedComponentRegistry->getNestedComponentsByScope();
        $js = $this->scriptBuilder->build($this->registry, $this->getGlobals(), $nestedComponents, $development);

        $scriptAttributes = $development ? ['id' => self::SCRIPT_ID] : [];

        // Add or update the script in AssetCollector
        $this->assetCollector->addInlineJavaScript(self::SCRIPT_ID, $js, $scriptAttributes, [
            'priority' => true,
            // a nonce or hash when the site sends a Content-Security-Policy, so it needs no 'unsafe-inline'
            'csp' => true,
        ]);
    }

    private function resolveGlobals(): void
    {
        if ($this->globalsResolved) {
            return;
        }

        $this->globalsResolved = true;

        $request = $this->getRequest();
        if ($request instanceof ServerRequestInterface) {
            $this->globalContext->initialize($request);
        }

        $this->globals = ClientPropsContextExtractor::extract($this->globalContext);
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
