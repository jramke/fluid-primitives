<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

use Jramke\FluidPrimitives\Utility\EnumUtility;

class TagAttributes implements \Countable, \Stringable
{
    protected $attributesString = '';
    protected $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->attributesString = $this->buildAttributesString($this->attributes);
    }

    public function count(): int
    {
        return count($this->attributes);
    }

    public function __toString(): string
    {
        return (string)$this->attributesString;
    }

    public function renderAsArray(array $attributes = []): array
    {
        if ($attributes === []) {
            if ($this->attributes === []) {
                return [];
            }

            $attributes = $this->attributes;
        }

        return $this->normalizeAttributes($attributes, static fn($key, $value) => htmlspecialchars((string)$value));
    }

    public function renderWithOnly(array $attributeKeys, bool $asArray = false): string|array
    {
        if ($this->attributes === []) {
            return $asArray ? [] : '';
        }

        $attributesToRender = $this->attributes;
        if ($attributeKeys !== []) {
            $attributesToRender = array_intersect_key($this->attributes, array_flip($attributeKeys));
        }

        if ($attributesToRender === []) {
            return $asArray ? [] : '';
        }

        return $asArray ? $this->renderAsArray($attributesToRender) : $this->buildAttributesString($attributesToRender);
    }

    public function renderWithSkip(array $attributeKeys, bool $asArray = false): string|array
    {
        if ($this->attributes === []) {
            return $asArray ? [] : '';
        }

        $attributesToRender = $this->attributes;
        if ($attributeKeys !== []) {
            $attributesToRender = array_diff_key($this->attributes, array_flip($attributeKeys));
        }

        if ($attributesToRender === []) {
            return $asArray ? [] : '';
        }

        return $asArray ? $this->renderAsArray($attributesToRender) : $this->buildAttributesString($attributesToRender);
    }

    protected function buildAttributesString(array $attributes): string
    {
        $parts = $this->normalizeAttributes($attributes, $this->buildSingleAttributeString(...));

        return implode(' ', $parts);
    }

    protected function normalizeAttributes(array $attributes, callable $valueFormatter): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, [0, '', '0'], true) || $value === null) {
                continue;
            }

            $value = EnumUtility::normalize($value);

            // convert boolean values to html boolean attributes unless they are aria- attributes
            if (!str_starts_with((string)$key, 'aria-') && is_bool($value)) {
                $value = $value ? '' : null;
                if ($value === null) {
                    continue;
                }
            }

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $result[$key] = $valueFormatter((string)$key, (string)$value);
        }

        return $result;
    }

    protected function buildSingleAttributeString(string $key, string $value): string
    {
        if ($value === '' || $value === '0') {
            return htmlspecialchars((string)$key);
        }
        return sprintf('%s="%s"', htmlspecialchars((string)$key), htmlspecialchars((string)$value));
    }

    public static function stringToArray(string $attributesString): array
    {
        if ($attributesString === '' || $attributesString === '0') {
            return [];
        }

        $attributes = [];
        $parts = explode(' ', trim($attributesString));
        foreach ($parts as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $attributes[trim($key)] = trim($value, '"');
            } else {
                $attributes[trim($part)] = true; // boolean attribute
            }
        }
        return $attributes;
    }
}
