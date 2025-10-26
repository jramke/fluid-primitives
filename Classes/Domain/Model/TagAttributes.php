<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Model;

class TagAttributes implements \Countable
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
        return $this->attributesString;
    }

    public function renderAsArray(): array
    {
        return $this->normalizeAttributes(
            $this->attributes,
            fn($key, $value) => htmlspecialchars($value)
        );
    }

    public function renderWithOnly(array $attributeKeys): string
    {
        if (empty($this->attributes)) {
            return '';
        }

        $attributesToRender = $this->attributes;

        if (!empty($attributeKeys)) {
            $attributesToRender = array_intersect_key($this->attributes, array_flip($attributeKeys));
        }
        if (empty($attributesToRender)) {
            return '';
        }
        return $this->buildAttributesString($attributesToRender);
    }

    public function renderWithSkip(array $attributeKeys): string
    {
        if (empty($this->attributes)) {
            return '';
        }

        $attributesToRender = $this->attributes;

        if (!empty($attributeKeys)) {
            $attributesToRender = array_diff_key($this->attributes, array_flip($attributeKeys));
        }
        if (empty($attributesToRender)) {
            return '';
        }
        return $this->buildAttributesString($attributesToRender);
    }

    protected function buildAttributesString(array $attributes): string
    {
        $parts = $this->normalizeAttributes(
            $attributes,
            fn($key, $value) => $this->buildSingleAttributeString($key, $value)
        );

        return implode(' ', $parts);
    }

    protected function normalizeAttributes(array $attributes, callable $valueFormatter): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if (empty($key) || $value === null) {
                continue;
            }

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

            $result[$key] = $valueFormatter((string)$key, (string)$value);
        }

        return $result;
    }

    protected function buildSingleAttributeString(string $key, string $value): string
    {
        if (empty($value)) {
            return htmlspecialchars((string)$key);
        }
        return sprintf('%s="%s"', htmlspecialchars((string)$key), htmlspecialchars((string)$value));
    }

    public static function stringToArray(string $attributesString): array
    {
        if (empty($attributesString)) {
            return [];
        }

        $attributes = [];
        $parts = explode(' ', trim($attributesString));
        foreach ($parts as $part) {
            if (strpos($part, '=') !== false) {
                [$key, $value] = explode('=', $part, 2);
                $attributes[trim($key)] = trim($value, '"');
            } else {
                $attributes[trim($part)] = true; // boolean attribute
            }
        }
        return $attributes;
    }
}
