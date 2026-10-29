<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use Jramke\FluidPrimitives\Exception\InvalidArgumentException;
use Jramke\FluidPrimitives\Utility\Typed;
use JsonSerializable;

/**
 * A heading of a page for a table of contents: the id of its element, its level and the text to link it with.
 * Only the value and the depth reach the client.
 */
final readonly class TocItem implements JsonSerializable
{
    /**
     * @param string $value The id of the heading element
     * @param int $depth The level of the heading, 2 for an h2
     * @param string $label The text of the link, for the template that renders it
     */
    public function __construct(
        public string $value,
        public int $depth,
        public string $label = '',
    ) {
        if ($value === '') {
            throw new InvalidArgumentException('A toc item needs a value, the id of its heading.', 1_788_100_054);
        }

        if ($depth < 1) {
            throw new InvalidArgumentException(
                sprintf('The depth of the toc item "%s" must be 1 or more, %d given.', $value, $depth),
                1_788_100_055,
            );
        }
    }

    /**
     * @param array<mixed> $entry An array with a `value` and a `depth`, and optionally a `label`
     */
    public static function fromArray(array $entry): self
    {
        $value = Typed::string($entry['value'] ?? null);

        return new self(
            $value,
            Typed::intOrNull($entry['depth'] ?? null) ?? throw new InvalidArgumentException(
                sprintf('The toc item "%s" needs an integer depth, like 2 for an h2.', $value),
                1_788_100_053,
            ),
            Typed::string($entry['label'] ?? null),
        );
    }

    /**
     * @return array{value: string, depth: int}
     */
    public function jsonSerialize(): array
    {
        return ['value' => $this->value, 'depth' => $this->depth];
    }
}
