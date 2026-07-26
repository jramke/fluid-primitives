<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ClipboardRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HydrationRegistry::getInstance()->clear();
    }

    #[Test]
    public function rendersEnglishTriggerLabelByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:clipboard.root value="Copy me">
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $this->assertStringContainsString('aria-label="Copy to clipboard"', $html);
    }

    #[Test]
    public function rendersGermanTriggerLabelWhenLocaleIsGerman(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:clipboard.root value="Copy me">
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $this->assertStringContainsString('aria-label="In Zwischenablage kopieren"', $html);
    }

    #[Test]
    public function prefersPropOverridesOverLocalizedDefaults(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: \'Link kopieren\'}">
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $this->assertStringContainsString('aria-label="Link kopieren"', $html);
    }

    #[Test]
    public function allowsDisablingTriggerAriaLabels(): void
    {
        $html = $this->renderTemplate('
            <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: false, triggerLabelCopied: false}">
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $this->assertStringNotContainsString('aria-label=', $html);
    }

    #[Test]
    public function includesMergedTranslationsInHydrationData(): void
    {
        $this->setRequestLocale('de_DE');

        $this->renderTemplate('
            <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: \'Link kopieren\', triggerLabelCopied: false}">
                <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
            </primitives:clipboard.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $clipboardData = array_values($hydrationData['clipboard'])[0];

        $this->assertSame(
            [
                'triggerLabelIdle' => 'Link kopieren',
                'triggerLabelCopied' => false,
            ],
            $clipboardData['props']['translations'],
        );
    }
}
