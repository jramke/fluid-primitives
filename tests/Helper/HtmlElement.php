<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Helper;

/**
 * The opening tag of a rendered element, so a test asserts on one element's own attributes: nothing in
 * another element, in text, or in a longer attribute name (`disabled` in `data-disabled`) can satisfy it.
 */
final readonly class HtmlElement
{
    /**
     * @param array<string, string> $attributes A bare attribute maps to ''.
     */
    private function __construct(
        public string $tag,
        public array $attributes,
    ) {}

    /**
     * @return list<self> Every element of the markup, in document order.
     */
    public static function parseAll(string $html): array
    {
        $tagPattern = '/<([a-z][a-z0-9-]*)((?:\s+[^\s"\'<>\/=]+(?:\s*=\s*(?:"[^"]*"|\'[^\']*\'))?)*)\s*\/?>/i';
        preg_match_all($tagPattern, $html, $tags, PREG_SET_ORDER);

        $elements = [];
        foreach ($tags as [, $tag, $attributeString]) {
            preg_match_all(
                '/([^\s"\'<>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'))?/',
                $attributeString,
                $attributes,
                PREG_SET_ORDER,
            );

            $parsed = [];
            foreach ($attributes as $attribute) {
                // a browser keeps the first of a repeated attribute and drops the rest
                $parsed[$attribute[1]] ??= html_entity_decode($attribute[2] ?? $attribute[3] ?? '', ENT_QUOTES);
            }

            $elements[] = new self(strtolower($tag), $parsed);
        }

        return $elements;
    }

    public function has(string $attribute): bool
    {
        return array_key_exists($attribute, $this->attributes);
    }

    public function get(string $attribute): ?string
    {
        return $this->attributes[$attribute] ?? null;
    }
}
