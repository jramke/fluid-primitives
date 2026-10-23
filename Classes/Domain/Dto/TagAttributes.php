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

        // raw on purpose: whatever renders the array (another TagAttributes, f:tag) escapes it once
        return $this->normalizeAttributes($attributes, static fn($key, $value) => $value);
    }

    /**
     * @param string[] $attributeKeys
     */
    public function renderWithOnly(array $attributeKeys, bool $asArray = false): string|array
    {
        return $this->renderFiltered($this->filterByName($attributeKeys, keep: true), $asArray);
    }

    /**
     * @param string[] $attributeKeys
     */
    public function renderWithSkip(array $attributeKeys, bool $asArray = false): string|array
    {
        return $this->renderFiltered($this->filterByName($attributeKeys, keep: false), $asArray);
    }

    /**
     * Attribute names are case-insensitive in HTML, so `HIDDEN` in a template skips a caller's `hidden`.
     *
     * @param string[] $names
     * @return array<string, mixed>
     */
    private function filterByName(array $names, bool $keep): array
    {
        if ($names === []) {
            return $this->attributes;
        }

        $names = array_map(strtolower(...), $names);

        return array_filter(
            $this->attributes,
            static fn(string $key) => in_array(strtolower($key), $names, strict: true) === $keep,
            ARRAY_FILTER_USE_KEY,
        );
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

        // Attribute values are genuinely heterogeneous (string, bool, array, enum, ...) - that's what
        // this whole method normalizes; is_bool()/is_array()/is_object() below handle each case.
        // @mago-expect analysis:mixed-assignment
        foreach ($attributes as $key => $value) {
            if ($key === '' || $value === null) {
                continue;
            }

            $this->assertValidName($key);

            // @mago-expect analysis:mixed-assignment
            $value = EnumUtility::normalize($value);

            // a boolean aria-* attribute is the token true/false
            if (is_bool($value) && str_starts_with($key, 'aria-')) {
                $value = $value ? 'true' : 'false';
            }

            // any other boolean is an html boolean attribute: bare when true, left out when false
            if (is_bool($value)) {
                if (!$value) {
                    continue;
                }
                $value = '';
            }

            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            if ($this->isScriptUrl($key, (string)$value)) {
                continue;
            }

            $result[$key] = $valueFormatter($key, (string)$value);
        }

        return $result;
    }

    /**
     * The same rule as Fluid's TagBuilder: escaping the name would keep a space, `=` or `/` in it, and the
     * rest of the name would become attributes of their own.
     */
    private function assertValidName(string $name): void
    {
        if (preg_match('/[^0-9A-Za-z!#\$%()*+,\.:;?@\[\]\^_`{|}~-]/', $name)) {
            throw new \InvalidArgumentException('Invalid attribute name provided: ' . $name, 1_788_100_041);
        }
    }

    /**
     * A `javascript:` or `vbscript:` URL, and a `data:` one where a link or form is followed, is left out like
     * `f:link` does. The browser drops tabs, newlines and leading control characters inside a URL, so they are
     * dropped here before the scheme is compared.
     */
    private function isScriptUrl(string $name, string $value): bool
    {
        $name = strtolower($name);
        if (!in_array(
            $name,
            ['href', 'src', 'action', 'formaction', 'poster', 'xlink:href', 'cite', 'data'],
            strict: true,
        )) {
            return false;
        }

        $url = strtolower((string)preg_replace('/[\x00-\x20]+/', replacement: '', subject: $value));
        if (str_starts_with($url, 'javascript:') || str_starts_with($url, 'vbscript:')) {
            return true;
        }

        return (
            str_starts_with($url, 'data:') &&
            in_array($name, ['href', 'action', 'formaction', 'xlink:href'], strict: true)
        );
    }

    protected function buildSingleAttributeString(string $key, string $value): string
    {
        if ($value === '') {
            return htmlspecialchars($key);
        }
        return sprintf('%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
    }
}
