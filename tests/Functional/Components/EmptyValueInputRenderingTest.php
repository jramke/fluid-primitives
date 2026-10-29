<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Domain\Dto\ListCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use Jramke\FluidPrimitives\Tests\Helper\HtmlElement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * A control that submits nothing while empty renders an empty-value input, so the name is always
 * submitted and Extbase can clear the property - under the name without `[]`, as core Fluid's own hidden
 * fields do, since Extbase maps `''` to an empty array and `['']` to one empty entry.
 */
final class EmptyValueInputRenderingTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: ?array{0: string, 1: bool}, 2: ?string}>
     */
    public static function controls(): iterable
    {
        $checkbox = '<primitives:checkbox.root %s><primitives:checkbox.hiddenInput /></primitives:checkbox.root>';
        $checkboxGroup = '
            <primitives:checkboxGroup.root name="tags[]" %s>
                <primitives:checkbox.root value="a"><primitives:checkbox.hiddenInput /></primitives:checkbox.root>
                <primitives:checkbox.root value="b"><primitives:checkbox.hiddenInput /></primitives:checkbox.root>
            </primitives:checkboxGroup.root>';
        $select = '<primitives:select.root collection="{collection}" %s><primitives:select.hiddenSelect /></primitives:select.root>';
        $combobox = '<primitives:combobox.root collection="{collection}" %s><primitives:combobox.hiddenInput /></primitives:combobox.root>';
        $datePicker = '<primitives:datePicker.root %s><primitives:datePicker.hiddenInput /></primitives:datePicker.root>';

        // checked, it stays enabled: without JavaScript, PHP keeps the value of the checkbox that comes after it
        yield 'checkbox' => [
            sprintf($checkbox, 'name="agree" defaultChecked="{true}"'),
            ['agree', false],
            'data-checkbox-hidden-input',
        ];
        yield 'disabled checkbox' => [
            sprintf($checkbox, 'name="agree" disabled="{true}"'),
            ['agree', true],
            'data-checkbox-hidden-input',
        ];
        // one of several under the name: their checkbox group submits the empty value once
        yield 'checkbox of a list' => [sprintf($checkbox, 'name="tags[]"'), null, 'data-checkbox-hidden-input'];
        yield 'switch' => [
            '<primitives:switch.root name="newsletter"><primitives:switch.hiddenInput /></primitives:switch.root>',
            ['newsletter', false],
            'data-switch-hidden-input',
        ];
        yield 'checkbox group' => [sprintf($checkboxGroup, ''), ['tags', false], 'data-checkbox-hidden-input'];
        yield 'disabled checkbox group' => [
            sprintf($checkboxGroup, 'disabled="{true}"'),
            ['tags', true],
            'data-checkbox-hidden-input',
        ];
        // Zag leaves an empty single select without a selected option, so it needs the input as well
        yield 'select' => [sprintf($select, 'name="tag"'), ['tag', false], 'data-select-hidden-select'];
        yield 'empty multiple select' => [
            sprintf($select, 'name="tags[]" multiple="{true}"'),
            ['tags', false],
            'data-select-hidden-select',
        ];
        // with a value it stays: PHP keeps the last value under a name, and `name[]` values replace a plain `name`
        yield 'multiple select with a value' => [
            sprintf($select, 'name="tags[]" multiple="{true}" defaultValue="{0: \'a\'}"'),
            ['tags', false],
            'data-select-hidden-select',
        ];
        // an empty combobox or date picker renders no value input, the empty-value input is all it submits
        yield 'empty multiple combobox' => [
            sprintf($combobox, 'name="tags[]" multiple="{true}"'),
            ['tags', false],
            null,
        ];
        yield 'empty single combobox' => [sprintf($combobox, 'name="tag"'), ['tag', false], null];
        yield 'multiple combobox with a value' => [
            sprintf($combobox, 'name="tags[]" multiple="{true}" defaultValue="{0: \'a\'}"'),
            ['tags', false],
            'data-combobox-hidden-input',
        ];
        yield 'empty date picker' => [sprintf($datePicker, 'name="date"'), ['date', false], null];
        yield 'date picker with a date' => [
            sprintf($datePicker, 'name="date" defaultValue="2024-01-15"'),
            ['date', false],
            'data-date-picker-hidden-input',
        ];
        yield 'disabled date picker' => [sprintf($datePicker, 'name="date" disabled="{true}"'), ['date', true], null];
    }

    /**
     * @param ?array{0: string, 1: bool} $expected name and disabled state of the empty-value input, `null` for none
     */
    #[Test]
    #[DataProvider('controls')]
    public function submitsAnEmptyValueBeforeTheControlUnderTheNameWithoutBrackets(
        string $template,
        ?array $expected,
        ?string $controlAttribute,
    ): void {
        $html = $this->renderTemplate($template, [
            'collection' => new ListCollection([['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]),
        ]);

        $elements = HtmlElement::parseAll($html);
        $controlIndex = $controlAttribute === null ? null : $this->firstIndexWith($elements, $controlAttribute);
        if ($controlAttribute !== null) {
            $this->assertNotNull($controlIndex, 'The control itself has to be rendered.');
        }

        $emptyValueInputs = $this->elementsWith($html, 'data-empty-value');
        if ($expected === null) {
            $this->assertSame([], $emptyValueInputs);
            return;
        }

        [$name, $disabled] = $expected;
        $this->assertCount(1, $emptyValueInputs);
        $input = $emptyValueInputs[0];
        $this->assertSame($name, $input->get('name'));
        $this->assertSame('', $input->get('value'));
        $this->assertSame($disabled, $input->has('disabled'));
        // PHP keeps the last value under a name, and `name[]` values replace a plain `name`
        if ($controlIndex !== null) {
            $this->assertLessThanOrEqual($controlIndex, $this->firstIndexWith($elements, 'data-empty-value'));
        }
    }

    /**
     * @param list<HtmlElement> $elements
     */
    private function firstIndexWith(array $elements, string $attribute): ?int
    {
        foreach ($elements as $index => $element) {
            if ($element->has($attribute)) {
                return $index;
            }
        }

        return null;
    }
}
