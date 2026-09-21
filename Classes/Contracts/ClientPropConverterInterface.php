<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contracts;

use TYPO3Fluid\Fluid\Core\ViewHelper\ArgumentDefinition;

/**
 * Converts a client-marked prop value whose class you don't control (e.g. an Extbase model) into a
 * JSON-encodable shape, for values that implement neither `JsonSerializable` nor
 * {@see ClientTypeAwareInterface}. Registered as a tagged service, collected by
 * {@see \Jramke\FluidPrimitives\Registry\ClientPropConverterRegistry}.
 *
 * `supports()` is called for every client-marked object value on the page - match narrowly, by
 * exact class/interface, never duck-typing, so a converter never silently transforms something it
 * shouldn't.
 */
interface ClientPropConverterInterface
{
    public function supports(mixed $value, ArgumentDefinition $definition): bool;

    /**
     * @return mixed JSON-encodable scalar/array
     */
    public function convert(mixed $value): mixed;

    /**
     * @return class-string A plain, never-instantiated, `#[TypeScript]`-attributed PHP class
     *   describing {@see convert()}'s result shape - reflected by spatie/typescript-transformer into
     *   real TypeScript, rather than hand-typed as a string with nothing checking it against what
     *   {@see convert()} actually returns.
     */
    public function getTsShapeClass(): string;
}
