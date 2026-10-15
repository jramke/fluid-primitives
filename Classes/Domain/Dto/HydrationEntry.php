<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

/**
 * One root component's data for client hydration, as {@see \Jramke\FluidPrimitives\Registry\HydrationRegistry}
 * keeps it - {@see toArray()} is the shape the client reads from `hydrationData`.
 */
final readonly class HydrationEntry
{
    /**
     * @param array<array-key, mixed> $ids
     * @param array<array-key, mixed> $props the client props besides `id` and `ids`
     */
    public function __construct(
        public string $id,
        public array $ids,
        public bool $autoMount,
        public array $props,
    ) {}

    /**
     * @return array{autoMount: bool, props: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'autoMount' => $this->autoMount,
            'props' => ['id' => $this->id, 'ids' => $this->ids, ...$this->props],
        ];
    }
}
