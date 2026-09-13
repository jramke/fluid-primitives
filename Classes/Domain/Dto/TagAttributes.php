<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use Jramke\FluidPrimitives\Utility\EnumUtility;

class TagAttributes implements \Countable, \Stringable
{
    protected string $attributesString = '';

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected array $attributes = [],
    ) {
        $this->attributesString = $this->buildAttributesString($this->attributes);
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    public function __toString(): string
    {
        return $this->attributesString;
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, string>
     */
    public function renderAsArray(array $attributes = []): array
    {
        if ($attributes === []) {
            if ($this->attributes === []) {
                return [];
            }

            $attributes = $this->attributes;
        }

        return $this->normalizeAttributes($attributes, static fn($key, $value) => htmlspecialchars($value));
    }

    /**
     * @param string[] $attributeKeys
     */
    public function renderWithOnly(array $attributeKeys, bool $asArray = false): string|array
    {
        $attributesToRender = $attributeKeys === []
            ? $this->attributes
            : array_intersect_key($this->attributes, array_flip($attributeKeys));

        return $this->renderFiltered($attributesToRender, $asArray);
    }

    /**
     * @param string[] $attributeKeys
     */
    public function renderWithSkip(array $attributeKeys, bool $asArray = false): string|array
    {
        $attributesToRender = $attributeKeys === []
            ? $this->attributes
            : array_diff_key($this->attributes, array_flip($attributeKeys));

        return $this->renderFiltered($attributesToRender, $asArray);
    }

    /**
     * @param array<string, mixed> $attributesToRender
     */
    private function renderFiltered(array $attributesToRender, bool $asArray): string|array
    {
        if ($attributesToRender === []) {
            return $asArray ? [] : '';
        }

        return $asArray ? $this->renderAsArray($attributesToRender) : $this->buildAttributesString($attributesToRender);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    protected function buildAttributesString(array $attributes): string
    {
        $parts = $this->normalizeAttributes($attributes, $this->buildSingleAttributeString(...));

        return implode(' ', $parts);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param callable(string, string): string $valueFormatter
     * @return array<string, string>
     */
    protected function normalizeAttributes(array $attributes, callable $valueFormatter): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if ($key === '' || $value === null) {
                continue;
            }

            $value = EnumUtility::normalize($value);

            // convert boolean values to html boolean attributes unless they are aria- attributes
            if (!str_starts_with($key, 'aria-') && is_bool($value)) {
                $value = $value ? '' : null;
                if ($value === null) {
                    continue;
                }
            }

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $result[$key] = $valueFormatter($key, (string)$value);
        }

        return $result;
    }

    protected function buildSingleAttributeString(string $key, string $value): string
    {
        if ($value === '') {
            return htmlspecialchars($key);
        }
        return sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
    }
}
