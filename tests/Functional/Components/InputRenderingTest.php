<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class InputRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersTheNativeInputWithTheAttributesItIsGiven(): void
    {
        // `pattern` is passed as a bound variable rather than an inline literal - a literal
        // containing `{3}`/`{4}` would be misparsed by Fluid as embedded object-accessor
        // expressions, same as any other string argument value with literal curly braces.
        $html = $this->renderTemplate('
            <primitives:input.root type="tel" defaultValue="555-1234" pattern="{pattern}" placeholder="555-0000" autocomplete="tel" maxLength="8"
                inputMode="{f:constant(name: \'Jramke\FluidPrimitives\Enum\InputMode::Numeric\')}">
                <primitives:input.input />
            </primitives:input.root>
        ', ['pattern' => '[0-9]{3}-[0-9]{4}']);

        $this->assertSame(1, count($this->elementsWith($html, 'data-input-root')));
        $input = $this->elementWith($html, 'data-input-input');
        $this->assertSame('input', $input->tag);
        $this->assertSame('tel', $input->get('type'));
        $this->assertSame('555-1234', $input->get('value'));
        $this->assertSame('[0-9]{3}-[0-9]{4}', $input->get('pattern'));
        $this->assertSame('numeric', $input->get('inputmode'));
        $this->assertSame('555-0000', $input->get('placeholder'));
        $this->assertSame('tel', $input->get('autocomplete'));
        $this->assertSame('8', $input->get('maxlength'));
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
            <primitives:input.root ' . $arguments . '>
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ');

        $this->assertSame(1, preg_match('/<span[^>]*data-input-word-count[^>]*>([^<]*)</', $html, $matches));
        $this->assertSame($expectedText, trim($matches[1]));
    }

    #[Test]
    public function shipsTheMergedTranslationsAndTheAnnounceDefaultsToTheClient(): void
    {
        $this->renderTemplate('
            <primitives:input.root maxLength="10" translations="{wordCount: \'%count% of %max%\'}">
                <primitives:input.input />
            </primitives:input.root>
        ');

        $inputData = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['input'])[0];

        $this->assertSame(
            ['wordCount' => '%count% of %max%', 'wordCountOver' => '%over% characters too many'],
            $inputData['props']['translations'],
        );
        $this->assertSame('block', $inputData['props']['maxLengthBehavior']);
        $this->assertSame(600, $inputData['props']['announceDebounce']);
        $this->assertTrue($inputData['props']['announce']);
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
            <primitives:input.root maxLength="5" ' . $arguments . '>
                <primitives:input.input />
            </primitives:input.root>
        ');

        $this->assertSame($expectedMaxlength, $this->elementWith($html, 'data-input-input')->get('maxlength'));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: string, 2: string, 3: bool, 4: string}>
     */
    public static function overLimitStates(): iterable
    {
        yield 'a value past the limit' => [null, 'warn', 'hello world', true, '6 characters too many'];
        yield 'code points like the server validator, not UTF-16 units' => [
            null,
            'invalid',
            'ab😀cd',
            false,
            '5 / 5 characters',
        ];
        yield 'emoji count once, so one more of them is over' => [
            null,
            'invalid',
            'ab😀cde',
            true,
            '1 characters too many',
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
            <primitives:input.root maxLength="5" maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::' .
            ucfirst($behavior) .
            '\')}" defaultValue="' .
            $defaultValue .
            '">
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ',
        );

        foreach (['root', 'input', 'word-count'] as $part) {
            $this->assertSame(
                $expectedOver,
                $this->elementWith($html, 'data-input-' . $part)->has('data-over-limit'),
                $part,
            );
        }
        $this->assertSame(1, preg_match('/<span[^>]*data-input-word-count[^>]*>([^<]*)</', $html, $matches));
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
        yield 'block counts UTF-16 units like the browser, an emoji is two' => [
            'maxLength="5"',
            'ab😀c',
            true,
            'block',
        ];
        yield 'the other behaviors count code points like the server, an emoji is one' => [
            'maxLength="5" maxLengthBehavior="{f:constant(name: \'Jramke\FluidPrimitives\Enum\MaxLengthBehavior::Warn\')}"',
            'ab😀c',
            false,
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
            <primitives:input.root ' .
        $arguments .
        ' defaultValue="' .
        $defaultValue .
        '">
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ');

        foreach (['root', 'input', 'word-count'] as $part) {
            $element = $this->elementWith($html, 'data-input-' . $part);
            $this->assertSame($expectedAtLimit, $element->has('data-at-limit'), $part);
            $this->assertSame($expectedBehavior, $element->get('data-max-length-behavior'), $part);
        }
    }
}
