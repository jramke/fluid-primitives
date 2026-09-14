<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;

/**
 * Shared by Context classes for components built around a `collection` prop (e.g. Select, Combobox).
 */
trait HasListCollectionTrait
{
    abstract public function get(string $key): mixed;

    public function getCollection(): ?ListCollection
    {
        // Narrowed immediately below via instanceof - no Typed:: equivalent for objects.
        // @mago-expect analysis:mixed-assignment
        $collection = $this->get('collection');
        return $collection instanceof ListCollection ? $collection : null;
    }
}
