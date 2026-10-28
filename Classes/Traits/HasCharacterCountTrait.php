<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Enum\MaxLengthBehavior;
use Jramke\FluidPrimitives\Utility\Typed;

/**
 * Shared by the Context classes of the text controls that count their characters against a
 * `maxLength` (Input, Textarea). It renders what the client would compute on the first paint: the
 * word count text, the native `maxlength` and how many characters the default value is over the limit.
 *
 * The abstract methods are this trait's contract with the using class: `get()` is satisfied by
 * `AbstractComponentContext`, `getTranslations()` returns the merged `wordCount` and `wordCountOver`
 * texts.
 */
trait HasCharacterCountTrait
{
    abstract public function get(string $key): mixed;

    /**
     * @return array{wordCount: string, wordCountOver: string}
     */
    abstract public function getTranslations(): array;

    // Not a `getMaxLengthBehavior()`: Fluid would take it for `context.maxLengthBehavior`, which is the prop.
    private function resolveMaxLengthBehavior(): MaxLengthBehavior
    {
        return MaxLengthBehavior::tryFrom(Typed::string($this->get('maxLengthBehavior'))) ?? MaxLengthBehavior::Block;
    }

    /**
     * The characters of the default value the way whoever enforces the limit counts them, like the
     * client does: `block` leaves it to the browser, which counts UTF-16 units, the other behaviors
     * to the server, which counts code points (`mb_strlen`, a `VARCHAR(n)`).
     */
    private function countDefaultValue(): int
    {
        $value = Typed::string($this->get('defaultValue'));

        if ($this->resolveMaxLengthBehavior() === MaxLengthBehavior::Block) {
            // a character outside the BMP is two UTF-16 units, an emoji for example
            return mb_strlen($value) + (int)preg_match_all('/[\x{10000}-\x{10FFFF}]/u', $value);
        }

        return mb_strlen($value);
    }

    /**
     * The value of the native `maxlength` attribute: only a `block` limit is enforced by the browser,
     * the other behaviors leave it off so a paste is never cut.
     */
    public function getNativeMaxLength(): ?int
    {
        if ($this->resolveMaxLengthBehavior() !== MaxLengthBehavior::Block) {
            return null;
        }

        return Typed::intOrNull($this->get('maxLength'));
    }

    /**
     * How many characters the default value is over `maxLength`. Always 0 for `block`, the browser
     * does not let a value get there.
     */
    public function getOverLimit(): int
    {
        $maxLength = Typed::intOrNull($this->get('maxLength'));
        if ($maxLength === null || $this->resolveMaxLengthBehavior() === MaxLengthBehavior::Block) {
            return 0;
        }

        return max(0, $this->countDefaultValue() - $maxLength);
    }

    /**
     * Whether the default value is exactly `maxLength` characters long.
     */
    public function isAtLimit(): bool
    {
        $maxLength = Typed::intOrNull($this->get('maxLength'));

        return $maxLength !== null && $this->countDefaultValue() === $maxLength;
    }

    /**
     * Server-rendered so the word count part isn't empty before hydration. `null` when there's no
     * `maxLength` to count against, or the translation is empty.
     */
    public function getWordCountText(): ?string
    {
        $maxLength = Typed::intOrNull($this->get('maxLength'));
        if ($maxLength === null) {
            return null;
        }

        $translations = $this->getTranslations();
        $template = Typed::string($translations['wordCount']);
        if ($template === '') {
            return null;
        }

        $over = $this->getOverLimit();
        $overTemplate = Typed::string($translations['wordCountOver']);
        if ($over > 0 && $overTemplate !== '') {
            $template = $overTemplate;
        }

        return str_replace(
            ['%count%', '%max%', '%over%'],
            [(string)$this->countDefaultValue(), (string)$maxLength, (string)$over],
            $template,
        );
    }
}
