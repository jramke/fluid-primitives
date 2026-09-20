<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Registry;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Tracks which root components render nested inside a "tracked scope" - a `ui:template` stencil,
 * or a FieldArray row - so the client can look nesting up as authoritative, PHP-recorded data
 * instead of inferring it from rendered DOM shape (which can't find a component that renders no DOM
 * element of its own, e.g. `Dialog`'s `Root`, or whose content has since portaled elsewhere). See
 * {@see recordNestedComponent}'s own docblock for the two call sites that populate this.
 *
 * A sibling to {@see HydrationRegistry} rather than folded into it - same singleton-per-request
 * shape (a hard `getInstance()`, for the same reason: `TemplateViewHelper`, one of its two writers,
 * is a Fluid ViewHelper instantiated by Fluid itself, outside the DI container, so it has no
 * constructor to inject this into), but a distinct concern with its own lifecycle, split out to
 * keep both classes' own method counts down.
 *
 * `public: true` - also constructor-injected into `HydrationRegistry`, and without this a
 * single-consumer service gets inlined by the compiled container, which breaks `getInstance()`'s
 * own `$container->get(self::class)` for every *other* caller (`TemplateViewHelper`,
 * `ComponentHydrationCollector`).
 */
#[Autoconfigure(public: true)]
class NestedComponentRegistry
{
    private static ?self $instance = null;

    /**
     * Keys of every tracked scope currently being rendered, outermost first - a stack rather than a
     * single value so a scope nested inside another (e.g. a Combobox's own itemTemplate authored
     * inside a FieldArray row's itemTemplate) records into both, not just the innermost.
     *
     * @var string[]
     */
    private array $trackingScopeStack = [];

    /**
     * @var array<string, array<int, array{name: string, id: string}>>
     */
    private array $nestedComponentsByScope = [];

    public static function getInstance(): self
    {
        if (!self::$instance instanceof self) {
            $container = GeneralUtility::getContainer();
            /** @var self $instance */
            $instance = $container->get(self::class);
            self::$instance = $instance;
        }
        return self::$instance;
    }

    /**
     * Opens a tracking scope keyed by `$key` - every root component that registers itself (via
     * {@see recordNestedComponent}) while this scope is open is recorded as nested inside it.
     * `TemplateViewHelper` calls this around its own `renderChildren()`, keyed by the stencil's own
     * generated id.
     */
    public function pushTrackingScope(string $key): void
    {
        $this->trackingScopeStack[] = $key;
        $this->nestedComponentsByScope[$key] ??= [];
    }

    public function popTrackingScope(): void
    {
        array_pop($this->trackingScopeStack);
    }

    /**
     * Records a just-registered root component (`$componentName`/`$rootId`, exactly as
     * {@see HydrationRegistry::add()} received them) against every currently-open tracking scope
     * ({@see pushTrackingScope}) and, when given, the explicit `$additionalScopeKey` - the single
     * call site in {@see \Jramke\FluidPrimitives\Service\Component\ComponentHydrationCollector}
     * covers both a `ui:template` stencil (via the stack) and a FieldArray row (via
     * `$additionalScopeKey`, since a real row has no `ui:template` wrapping it to push/pop a scope)
     * in one call. A no-op when neither applies (the common case for a component rendered outside
     * any tracked scope).
     */
    public function recordNestedComponent(
        string $componentName,
        string $rootId,
        ?string $additionalScopeKey = null,
    ): void {
        $keys = $this->trackingScopeStack;
        if ($additionalScopeKey !== null) {
            $keys[] = $additionalScopeKey;
        }

        foreach (array_unique($keys) as $key) {
            $this->nestedComponentsByScope[$key][] = ['name' => $componentName, 'id' => $rootId];
        }
    }

    /**
     * @return array<string, array<int, array{name: string, id: string}>>
     */
    public function getNestedComponentsByScope(): array
    {
        return $this->nestedComponentsByScope;
    }

    public function clear(): void
    {
        $this->trackingScopeStack = [];
        $this->nestedComponentsByScope = [];
    }
}
