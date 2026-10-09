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
     * @param array<string, string> $attributes A bare attribute maps to ''. The first of a repeated one, like a browser keeps it.
     * @param list<string> $repeatedAttributes The attributes the tag writes more than once, which is a parse error.
     */
    private function __construct(
        public string $tag,
        public array $attributes,
        public array $repeatedAttributes = [],
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
            $repeated = [];
            foreach ($attributes as $attribute) {
                if (array_key_exists($attribute[1], $parsed)) {
                    $repeated[] = $attribute[1];
                    continue;
                }
                $parsed[$attribute[1]] = html_entity_decode($attribute[2] ?? $attribute[3] ?? '', ENT_QUOTES);
            }

            $elements[] = new self(strtolower($tag), $parsed, array_values(array_unique($repeated)));
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
