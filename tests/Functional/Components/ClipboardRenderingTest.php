<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Owns the translation mechanism every component with `HasTranslationsTrait` shares (locale default, prop override)
 * - the other components only prove their own keys exist.
 */
final class ClipboardRenderingTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{0: ?string, 1: string, 2: string}>
     */
    public static function triggerLabels(): iterable
    {
        yield 'the English default' => [null, '', 'Copy to clipboard'];
        yield 'the German default for a German request' => ['de_DE', '', 'In Zwischenablage kopieren'];
        yield 'an override beats the localized default' => [
            'de_DE',
            'translations="{triggerLabelIdle: \'Link kopieren\'}"',
            'Link kopieren',
        ];
    }

    #[Test]
    #[DataProvider('triggerLabels')]
    public function labelsTheTriggerForScreenReadersInTheLanguageOfTheRequest(
        ?string $locale,
        string $arguments,
        string $expectedLabel,
    ): void {
        if ($locale !== null) {
            $this->setRequestLocale($locale);
        }

        $html = $this->renderTemplate('
            <primitives:clipboard.root value="Copy me" ' .
        $arguments .
        '>
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $this->assertSame($expectedLabel, $this->elementWith($html, 'data-clipboard-trigger')->get('aria-label'));
    }

    #[Test]
    public function shipsAnOverrideAndTheLocalizedDefaultOfTheOtherLabelToTheClient(): void
    {
        $this->setRequestLocale('de_DE');

        $this->renderTemplate('
            <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: \'Link kopieren\'}">
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $clipboardData = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['clipboard'])[0];

        $this->assertSame(
            ['triggerLabelIdle' => 'Link kopieren', 'triggerLabelCopied' => 'In Zwischenablage kopiert'],
            $clipboardData['props']['translations'],
        );
    }
}
