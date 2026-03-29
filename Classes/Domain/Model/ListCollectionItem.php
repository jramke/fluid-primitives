<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

use ArrayAccess;
use JsonSerializable;

/**
 * Normalized list item with consistent value, label, and disabled properties.
 * Provides array-style access for Fluid template compatibility.
 *
 * @implements ArrayAccess<string, mixed>
 */
class ListCollectionItem implements ArrayAccess, JsonSerializable
{
    public function __construct(
        public readonly string $value,
        public readonly string $label,
        public readonly bool $disabled,
        public readonly array|object $original,
    ) {}

    // ArrayAccess implementation for Fluid template compatibility

    public function offsetExists(mixed $offset): bool
    {
        return in_array($offset, ['value', 'label', 'disabled', 'original'], true);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return match ($offset) {
            'value' => $this->value,
            'label' => $this->label,
            'disabled' => $this->disabled,
            'original' => $this->original,
            default => null,
        };
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        // Immutable - do nothing
    }

    public function offsetUnset(mixed $offset): void
    {
        // Immutable - do nothing
    }

    public function jsonSerialize(): mixed
    {
        return [
            'value' => $this->value,
            'label' => $this->label,
            'disabled' => $this->disabled,
            'original' => $this->original,
        ];
    }
}
