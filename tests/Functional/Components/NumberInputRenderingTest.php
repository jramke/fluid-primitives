<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class NumberInputRenderingTest extends FunctionalTestCase
{
    private const string NUMBER_INPUT = '
        <primitives:numberInput.root %s>
            <primitives:numberInput.control>
                <primitives:numberInput.incrementTrigger>+</primitives:numberInput.incrementTrigger>
                <primitives:numberInput.input />
                <primitives:numberInput.decrementTrigger>-</primitives:numberInput.decrementTrigger>
            </primitives:numberInput.control>
        </primitives:numberInput.root>
    ';

    /**
     * @return iterable<string, array{0: ?string, 1: string, 2: string, 3: string}>
     */
    public static function triggerLabels(): iterable
    {
        yield 'English by default' => [null, '', 'Increment value', 'Decrement value'];
        yield 'German for a German request' => ['de_DE', '', 'Wert erhöhen', 'Wert verringern'];
        yield 'an override replaces one label and keeps the localized default of the other' => [
            'de_DE',
            'translations="{incrementLabel: \'Steigern\'}"',
            'Steigern',
            'Wert verringern',
        ];
    }

    #[Test]
    #[DataProvider('triggerLabels')]
    public function labelsTheTriggersInTheLanguageOfTheRequest(
        ?string $locale,
        string $arguments,
        string $incrementLabel,
        string $decrementLabel,
    ): void {
        if ($locale !== null) {
            $this->setRequestLocale($locale);
        }

        $html = $this->renderTemplate(sprintf(self::NUMBER_INPUT, $arguments));

        $this->assertSame(
            $incrementLabel,
            $this->elementWith($html, 'data-number-input-increment-trigger')->get('aria-label'),
        );
        $this->assertSame(
            $decrementLabel,
            $this->elementWith($html, 'data-number-input-decrement-trigger')->get('aria-label'),
        );
    }

    #[Test]
    public function shipsTheMergedTranslationsToTheClient(): void
    {
        $this->setRequestLocale('de_DE');

        $this->renderTemplate(sprintf(self::NUMBER_INPUT, 'translations="{incrementLabel: \'Steigern\'}"'));

        $numberInputData = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['number-input'])[0];

        $this->assertSame(
            ['incrementLabel' => 'Steigern', 'decrementLabel' => 'Wert verringern'],
            $numberInputData['props']['translations'],
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, ?string>, 2: bool, 3: bool}>
     */
    public static function values(): iterable
    {
        yield 'inside the range' => [
            'defaultValue="5" min="0" max="10"',
            ['value' => '5', 'aria-valuenow' => '5', 'aria-valuemin' => '0', 'aria-valuemax' => '10'],
            false,
            false,
        ];
        yield 'a zero at the minimum' => [
            'defaultValue="0" min="0" max="10"',
            ['value' => '0', 'aria-valuenow' => '0', 'aria-valuemin' => '0', 'aria-valuemax' => '10'],
            false,
            true,
        ];
        yield 'at the maximum' => [
            'defaultValue="10" min="0" max="10"',
            ['value' => '10', 'aria-valuenow' => '10', 'aria-valuemin' => '0', 'aria-valuemax' => '10'],
            true,
            false,
        ];
        yield 'no value and no range' => [
            '',
            ['value' => null, 'aria-valuenow' => null, 'aria-valuemin' => null, 'aria-valuemax' => null],
            false,
            false,
        ];
    }

    /**
     * @param array<string, ?string> $expectedAttributes
     */
    #[Test]
    #[DataProvider('values')]
    public function rendersTheValueAndTheRangeBeforeHydrationAndDisablesTheTriggerThatCannotMoveFurther(
        string $arguments,
        array $expectedAttributes,
        bool $incrementDisabled,
        bool $decrementDisabled,
    ): void {
        $html = $this->renderTemplate(sprintf(self::NUMBER_INPUT, $arguments));

        $input = $this->elementWith($html, 'data-number-input-input');
        foreach ($expectedAttributes as $name => $value) {
            $this->assertSame($value, $input->get($name), $name);
        }
        $this->assertSame(
            $incrementDisabled,
            $this->elementWith($html, 'data-number-input-increment-trigger')->has('disabled'),
        );
        $this->assertSame(
            $decrementDisabled,
            $this->elementWith($html, 'data-number-input-decrement-trigger')->has('disabled'),
        );
    }
}
