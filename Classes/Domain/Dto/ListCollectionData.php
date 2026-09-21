<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The wire shape {@see ListCollection::jsonSerialize()} actually produces - not the same as
 * `@zag-js/collection`'s own `ListCollection<T>` instance shape a primitive's `transformProps()`
 * constructs *from* this (see the plan's "Wire type vs. machine type" note). Never instantiated,
 * only reflected by `ui:generate-hydration-types` via the `#[TypeScript]` attribute - the single,
 * IDE/static-analysis-checkable source of truth Select and Combobox both generate their own
 * `collection` prop type from, replacing the previously hand-maintained duplicate interface in
 * `Resources/Private/Client/src/types.ts`.
 */
#[TypeScript]
final class ListCollectionData
{
    // list<mixed>, not the PHP constructor's own array<array-key, ...> shape: the wire value
    // (jsonSerialize()'s own $this->items) is a JSON-encoded, re-indexed list by the time it
    // reaches the client, and the consuming TS side (getListCollectionFromHydrationData()) expects
    // a real array (T[]), not an object keyed by array-key.
    /** @var list<mixed> */
    public array $items;

    public int $size;

    public ?string $first;

    public ?string $last;

    public ?string $itemToValueKey;

    public ?string $itemToStringKey;

    public ?string $isItemDisabledKey;

    public ?string $groupByKey;

    // Matches ListCollection::__construct()'s own runtime validation - a bare string can only ever
    // be 'asc'/'desc' there (anything else throws server-side), never an arbitrary string. Spelled
    // out via the attribute rather than a @var docblock: spatie's docblock type resolver doesn't
    // transpile PHPStan literal-string const types (confirmed empirically - it falls back to
    // `unknown`), so a plain `array|string|null` type is the most precision it can infer on its own.
    #[LiteralTypeScriptType("'asc' | 'desc' | string[] | null")]
    public array|string|null $groupSort;
}
