<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NumberInputRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HydrationRegistry::getInstance()->clear();
    }

    #[Test]
    public function rendersEnglishLabelsByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:numberInput.root>
                <primitives:numberInput.control>
                    <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                    <primitives:numberInput.input />
                    <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                </primitives:numberInput.control>
            </primitives:numberInput.root>
        ');

        $this->assertStringContainsString('aria-label="Increment value"', $html);
        $this->assertStringContainsString('aria-label="Decrement value"', $html);
    }

    #[Test]
    public function rendersGermanLabelsWhenLocaleIsGerman(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:numberInput.root>
                <primitives:numberInput.control>
                    <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                    <primitives:numberInput.input />
                    <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                </primitives:numberInput.control>
            </primitives:numberInput.root>
        ');

        $this->assertStringContainsString('aria-label="Wert erhöhen"', $html);
        $this->assertStringContainsString('aria-label="Wert verringern"', $html);
    }

    #[Test]
    public function prefersPropOverridesOverLocalizedDefaults(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:numberInput.root translations="{incrementLabel: \'Steigern\', decrementLabel: \'Senken\'}">
                <primitives:numberInput.control>
                    <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                    <primitives:numberInput.input />
                    <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                </primitives:numberInput.control>
            </primitives:numberInput.root>
        ');

        $this->assertStringContainsString('aria-label="Steigern"', $html);
        $this->assertStringContainsString('aria-label="Senken"', $html);
    }

    #[Test]
    public function includesMergedTranslationsInHydrationData(): void
    {
        $this->setRequestLocale('de_DE');

        $this->renderTemplate('
            <primitives:numberInput.root translations="{incrementLabel: \'Steigern\'}">
                <primitives:numberInput.control>
                    <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                    <primitives:numberInput.input />
                    <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
                </primitives:numberInput.control>
            </primitives:numberInput.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $numberInputData = array_values($hydrationData['number-input'])[0];

        $this->assertSame(
            [
                'incrementLabel' => 'Steigern',
                'decrementLabel' => 'Wert verringern',
            ],
            $numberInputData['props']['translations'],
        );
    }
}
