<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

/**
 * An opening tag found in a template source by {@see \Jramke\FluidPrimitives\Utility\TemplateTagScanner}.
 */
final readonly class ScannedTag
{
    /**
     * @param int $end Offset right behind the closing `>`
     * @param array<int, string> $regions The `{...}` written directly in the tag, by their offset
     * @param list<string> $attributeNames What the tag sets itself, literally or in an `f:if`
     */
    public function __construct(
        public string $name,
        public int $end,
        public array $regions,
        public array $attributeNames,
    ) {}
}
