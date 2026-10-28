<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class TextareaRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersTheNativeTextareaWithTheAttributesItIsGiven(): void
    {
        $html = $this->renderTemplate('
            <primitives:textarea.root defaultValue="hello world" rows="6" maxLength="140" placeholder="Type your message here..." autocomplete="street-address">
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $this->assertSame(1, count($this->elementsWith($html, 'data-textarea-root')));
        $textarea = $this->elementWith($html, 'data-textarea-textarea');
        $this->assertSame('textarea', $textarea->tag);
        $this->assertSame('6', $textarea->get('rows'));
        $this->assertSame('140', $textarea->get('maxlength'));
        $this->assertSame('Type your message here...', $textarea->get('placeholder'));
        $this->assertSame('street-address', $textarea->get('autocomplete'));
        // the default value is the content of a textarea, not an attribute
        $this->assertStringContainsString('>hello world</textarea>', $html);
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string, 2: string}>
     */
    public static function wordCounts(): iterable
    {
        yield 'the length against the maximum' => [null, 'defaultValue="hello" maxLength="10"', '5 / 10 characters'];
        yield 'nothing without a maximum' => [null, 'defaultValue="hello"', ''];
        yield 'nothing when the translation is empty' => [
            null,
            'defaultValue="hello" maxLength="10" translations="{wordCount: \'\'}"',
            '',
        ];
        yield 'German for a German request' => ['de_DE', 'defaultValue="hallo" maxLength="10"', '5 / 10 Zeichen'];
    }

    #[Test]
    #[DataProvider('wordCounts')]
    public function rendersTheWordCountTextBeforeHydrationSoItDoesNotFlash(
        ?string $locale,
        string $arguments,
        string $expectedText,
    ): void {
        if ($locale !== null) {
            $this->setRequestLocale($locale);
        }

        $html = $this->renderTemplate('
            <primitives:textarea.root ' . $arguments . '>
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ');

        $this->assertSame(1, preg_match('/<span[^>]*data-textarea-word-count[^>]*>([^<]*)</', $html, $matches));
        $this->assertSame($expectedText, trim($matches[1]));
    }

    #[Test]
    public function shipsTheMergedTranslationsTheAnnounceDefaultsAndTheSubmitShortcutToTheClient(): void
    {
        $this->renderTemplate('
            <primitives:textarea.root maxLength="10" translations="{wordCount: \'%count% of %max%\'}"
                submitOn="{f:constant(name: \'Jramke\FluidPrimitives\Enum\TextareaSubmitOn::ModEnter\')}">
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $textareaData = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['textarea'])[0];

        $this->assertSame(
            ['wordCount' => '%count% of %max%', 'wordCountOver' => 'Characters over the limit: %over%'],
            $textareaData['props']['translations'],
        );
        $this->assertSame('block', $textareaData['props']['maxLengthBehavior']);
        $this->assertSame(600, $textareaData['props']['announceDebounce']);
        $this->assertTrue($textareaData['props']['announce']);
        // the enum is unwrapped to the plain string the client compares against (textarea.connect.ts)
        $this->assertSame('mod+enter', $textareaData['props']['submitOn']);
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string}>
     */
    public static function maxLengthBehaviors(): iterable
    {
        yield 'block is the default' => ['', '5'];
        yield 'block' => [
            'maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Block\')}"',
            '5',
        ];
        yield 'warn lets a paste through' => [
            'maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Warn\')}"',
            null,
        ];
        yield 'invalid lets a paste through' => [
            'maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Invalid\')}"',
            null,
        ];
    }

    #[Test]
    #[DataProvider('maxLengthBehaviors')]
    public function leavesTheLimitToTheBrowserOnlyForTheBlockBehavior(
        string $arguments,
        ?string $expectedMaxlength,
    ): void {
        $html = $this->renderTemplate('
            <primitives:textarea.root maxLength="5" ' . $arguments . '>
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $this->assertSame($expectedMaxlength, $this->elementWith($html, 'data-textarea-textarea')->get('maxlength'));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string, 2: string, 3: bool, 4: string}>
     */
    public static function overLimitStates(): iterable
    {
        yield 'a value past the limit' => [null, 'warn', 'hello world', true, 'Characters over the limit: 6'];
        yield 'UTF-16 units like the browser, an emoji is two' => [
            null,
            'invalid',
            'ab😀cd',
            true,
            'Characters over the limit: 1',
        ];
        yield 'a value within the limit' => [null, 'warn', 'hello', false, '5 / 5 characters'];
        yield 'block can never be over, the browser stops it' => [
            null,
            'block',
            'hello world',
            false,
            '11 / 5 characters',
        ];
        yield 'German for a German request' => ['de_DE', 'warn', 'hallo welt', true, '5 Zeichen zu viel'];
    }

    #[Test]
    #[DataProvider('overLimitStates')]
    public function marksAnOverLongDefaultValueBeforeHydrationOnTheRootTheControlAndTheCount(
        ?string $locale,
        string $behavior,
        string $defaultValue,
        bool $expectedOver,
        string $expectedText,
    ): void {
        if ($locale !== null) {
            $this->setRequestLocale($locale);
        }

        $html = $this->renderTemplate(
            '
            <primitives:textarea.root maxLength="5" maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::' .
            ucfirst($behavior) .
            '\')}" defaultValue="' .
            $defaultValue .
            '">
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ',
        );

        foreach (['root', 'textarea', 'word-count'] as $part) {
            $this->assertSame(
                $expectedOver,
                $this->elementWith($html, 'data-textarea-' . $part)->has('data-over-limit'),
                $part,
            );
        }
        $this->assertSame(1, preg_match('/<span[^>]*data-textarea-word-count[^>]*>([^<]*)</', $html, $matches));
        $this->assertSame($expectedText, trim($matches[1]));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool, 3: ?string}>
     */
    public static function limitAttributes(): iterable
    {
        yield 'exactly at the limit' => ['maxLength="5"', 'hello', true, 'block'];
        yield 'the behavior is on the parts' => [
            'maxLength="5" maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Warn\')}"',
            'hello',
            true,
            'warn',
        ];
        yield 'past the limit is not at it' => [
            'maxLength="5" maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Invalid\')}"',
            'hello!',
            false,
            'invalid',
        ];
        yield 'an emoji is two UTF-16 units' => [
            'maxLength="5"',
            'ab😀c',
            true,
            'block',
        ];
        yield 'the other behaviors count UTF-16 units too' => [
            'maxLength="5" maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Warn\')}"',
            'ab😀c',
            true,
            'warn',
        ];
        yield 'nothing without a maximum' => ['', 'hello', false, null];
    }

    #[Test]
    #[DataProvider('limitAttributes')]
    public function marksTheBehaviorAndWhetherTheValueIsAtTheLimitOnTheRootTheControlAndTheCount(
        string $arguments,
        string $defaultValue,
        bool $expectedAtLimit,
        ?string $expectedBehavior,
    ): void {
        $html = $this->renderTemplate('
            <primitives:textarea.root ' .
        $arguments .
        ' defaultValue="' .
        $defaultValue .
        '">
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ');

        foreach (['root', 'textarea', 'word-count'] as $part) {
            $element = $this->elementWith($html, 'data-textarea-' . $part);
            $this->assertSame($expectedAtLimit, $element->has('data-at-limit'), $part);
            $this->assertSame($expectedBehavior, $element->get('data-max-length-behavior'), $part);
        }
    }
}
