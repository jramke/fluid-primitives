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
        yield 'nothing when the translation is switched off' => [
            null,
            'defaultValue="hello" maxLength="10" translations="{wordCount: false}"',
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

        $this->assertSame(['wordCount' => '%count% of %max%'], $inputData['props']['translations']);
        $this->assertSame(600, $inputData['props']['announceDebounce']);
        $this->assertTrue($inputData['props']['announce']);
    }
}
