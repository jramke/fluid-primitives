<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The one request-wide context every component can read through `{context.globals.<key>}` or
 * `$this->getGlobals()`. Its getters marked `#[ExposeToClient]` are written once into
 * `window.FluidPrimitives.globals`.
 *
 * A project replaces the library default by putting `#[AsAlias(GlobalContextInterface::class, public: true)]`
 * on its own class.
 *
 * @extends \ArrayAccess<string, mixed>
 */
interface GlobalContextInterface extends \ArrayAccess
{
    /**
     * Gives the context the request of the current render. Idempotent: the first call wins.
     */
    public function initialize(ServerRequestInterface $request): void;

    /**
     * The value of the getter named after the first segment of `$key` (`designTokens` calls
     * `getDesignTokens()`), then walks the remaining segments through the returned array.
     */
    public function get(string $key): mixed;

    public function has(string $key): bool;

    /**
     * `null` before {@see initialize()}, e.g. in an isolated render without a TYPO3 request.
     */
    public function getRequest(): ?ServerRequestInterface;
}
