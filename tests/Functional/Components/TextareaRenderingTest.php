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

        $this->assertSame(['wordCount' => '%count% of %max%'], $textareaData['props']['translations']);
        $this->assertSame(600, $textareaData['props']['announceDebounce']);
        $this->assertTrue($textareaData['props']['announce']);
        // the enum is unwrapped to the plain string the client compares against (textarea.connect.ts)
        $this->assertSame('mod+enter', $textareaData['props']['submitOn']);
    }
}
