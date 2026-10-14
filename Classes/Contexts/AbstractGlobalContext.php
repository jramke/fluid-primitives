<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Utility\Typed;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base class of a global context: public `get<Key>()` getters, found by `get('key')` and by Fluid's
 * `{context.globals.key}`. Results are memoised, an instance lives as long as the request.
 *
 * Getters run for server-side reads as well as for the client, so they must not depend on anything
 * that varies per user: the values end up in the page cache.
 */
abstract class AbstractGlobalContext implements GlobalContextInterface
{
    private ?ServerRequestInterface $request = null;

    /** @var array<string, mixed> */
    private array $resolved = [];

    public function initialize(ServerRequestInterface $request): void
    {
        $this->request ??= $request;
    }

    public function getRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    public function get(string $key): mixed
    {
        $segments = explode('.', $key);
        $first = array_shift($segments);

        if (!array_key_exists($first, $this->resolved)) {
            $getter = 'get' . ucfirst($first);
            if ($first === '' || !method_exists($this, $getter)) {
                return null;
            }

            // The getter is looked up by the name in the template, which is the point of this class.
            // @mago-expect analysis:string-member-selector
            $this->resolved[$first] = $this->{$getter}();
        }

        // Each level of a nested value is genuinely mixed.
        // @mago-expect analysis:mixed-assignment
        $value = $this->resolved[$first];
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            // @mago-expect analysis:mixed-assignment
            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->has(Typed::string($offset));
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->get(Typed::string($offset));
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('The global context is read only.', 1_788_200_006);
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('The global context is read only.', 1_788_200_006);
    }
}
