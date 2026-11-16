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
                <primitives:numberInput.hiddenInput />
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
            ['incrementLabel' => 'Steigern', 'decrementLabel' => 'Wert verringern', 'roleDescription' => 'Zahlenfeld'],
            $numberInputData['props']['translations'],
        );
    }

    #[Test]
    public function shipsTheOwnLocaleOtherwiseTheGlobalOne(): void
    {
        $this->setRequestLocale('de_DE');

        $this->renderTemplate(sprintf(self::NUMBER_INPUT, 'locale="fr-CH"'));
        $this->assertSame('fr-CH', $this->numberInputProps()['locale']);

        HydrationRegistry::getInstance()->clear();
        $this->renderTemplate(sprintf(self::NUMBER_INPUT, ''));
        $this->assertSame('de-DE', $this->numberInputProps()['locale']);
    }

    /**
     * @return array<string, mixed>
     */
    private function numberInputProps(): array
    {
        return array_values(HydrationRegistry::getInstance()->getAll()['primitives']['number-input'])[0]['props'];
    }

    /**
     * @return iterable<string, array{0: string, 1: ?string, 2: array<string, ?string>, 3: bool, 4: bool}>
     */
    public static function values(): iterable
    {
        yield 'inside the range' => [
            'defaultValue="5" min="0" max="10"',
            '5',
            ['min' => '0', 'max' => '10'],
            false,
            false,
        ];
        yield 'a zero at the minimum' => [
            'defaultValue="0" min="0" max="10"',
            '0',
            ['min' => '0', 'max' => '10'],
            false,
            true,
        ];
        yield 'at the maximum' => [
            'defaultValue="10" min="0" max="10"',
            '10',
            ['min' => '0', 'max' => '10'],
            true,
            false,
        ];
        yield 'no value and no range' => ['', null, ['min' => null, 'max' => null], false, false];
    }

    /**
     * @param array<string, ?string> $range
     */
    #[Test]
    #[DataProvider('values')]
    public function rendersTheValueAndTheRangeBeforeHydrationAndDisablesTheTriggerThatCannotMoveFurther(
        string $arguments,
        ?string $value,
        array $range,
        bool $incrementDisabled,
        bool $decrementDisabled,
    ): void {
        $html = $this->renderTemplate(sprintf(self::NUMBER_INPUT, $arguments));

        $this->assertSame($value, $this->elementWith($html, 'data-number-input-input')->get('value'));
        $hiddenInput = $this->elementWith($html, 'data-number-input-hidden-input');
        $this->assertSame($value, $hiddenInput->get('value'));
        foreach ($range as $name => $expected) {
            $this->assertSame($expected, $hiddenInput->get($name), $name);
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

    #[Test]
    public function submitsTheNameThroughTheHiddenInputAndNamesTheInputAsANumberField(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate(sprintf(self::NUMBER_INPUT, 'name="price" defaultValue="1.5"'));

        $input = $this->elementWith($html, 'data-number-input-input');
        // a text field, so VoiceOver on iOS reaches the triggers: no spinbutton and its aria-value*
        $this->assertFalse($input->has('role'));
        $this->assertFalse($input->has('aria-valuenow'));
        $this->assertFalse($input->has('name'));
        $this->assertSame('Zahlenfeld', $input->get('aria-roledescription'));
        $hiddenInput = $this->elementWith($html, 'data-number-input-hidden-input');
        $this->assertSame('price', $hiddenInput->get('name'));
        $this->assertSame('1.5', $hiddenInput->get('value'));
        $this->assertSame(1.5, $this->numberInputProps()['defaultValue']);
    }

    #[Test]
    public function marksTheControlDisabledAndInvalidButNotFocusedBeforeTheClientKnows(): void
    {
        $html = $this->renderTemplate(sprintf(self::NUMBER_INPUT, 'disabled="{true}" invalid="{true}"'));

        $control = $this->elementWith($html, 'data-number-input-control');
        $this->assertTrue($control->has('data-disabled'));
        $this->assertTrue($control->has('data-invalid'));
        // Zag sets it only while the control has focus
        $this->assertFalse($control->has('data-focus'));
    }
}
