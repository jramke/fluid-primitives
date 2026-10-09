<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class RadioGroupRenderingTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function groupFlags(): iterable
    {
        yield 'no flags' => ['', false];
        yield 'every flag' => ['disabled="{true}" readOnly="{true}" required="{true}" invalid="{true}"', true];
    }

    #[Test]
    #[DataProvider('groupFlags')]
    public function rendersTheRadiogroupRoleAndItsFlagsOnTheRoot(string $arguments, bool $flagged): void
    {
        $html = $this->renderTemplate('
            <primitives:radioGroup.root ' . $arguments . '>
                <primitives:radioGroup.item value="option-1">
                    <primitives:radioGroup.itemControl />
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ');

        $group = $this->elementWith($html, 'data-radio-group-root');
        $this->assertSame('radiogroup', $group->get('role'));

        $expected = $flagged ? 'true' : 'false';
        $this->assertSame($expected, $group->get('aria-required'));
        $this->assertSame($expected, $group->get('aria-disabled'));
        $this->assertSame($expected, $group->get('aria-readonly'));
        foreach (['data-disabled', 'data-readonly', 'data-invalid'] as $flag) {
            $this->assertSame($flagged, $group->has($flag), $flag);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function defaultValues(): iterable
    {
        yield 'nothing selected' => ['', ['unchecked', 'unchecked']];
        yield 'the second item selected' => ['defaultValue="option-2"', ['unchecked', 'checked']];
    }

    /**
     * @param list<string> $states
     */
    #[Test]
    #[DataProvider('defaultValues')]
    public function checksTheItemWhoseValueIsTheDefaultValue(string $arguments, array $states): void
    {
        $html = $this->renderTemplate('
            <primitives:radioGroup.root name="plan" ' . $arguments . '>
                <primitives:radioGroup.item value="option-1">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                    <primitives:radioGroup.itemHiddenInput />
                </primitives:radioGroup.item>
                <primitives:radioGroup.item value="option-2">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 2</primitives:radioGroup.itemText>
                    <primitives:radioGroup.itemHiddenInput />
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ');

        // the client finds each item's parts by this value
        $items = $this->elementsByValue($html, 'data-radio-group-item');
        $this->assertSame(['option-1', 'option-2'], array_keys($items));
        $this->assertSame(
            $states,
            array_values(array_map(static fn($item): ?string => $item->get('data-state'), $items)),
        );
        foreach (['data-radio-group-item-control', 'data-radio-group-item-text'] as $part) {
            $this->assertSame(
                ['option-1' => $states[0], 'option-2' => $states[1]],
                array_map(
                    static fn($element): ?string => $element->get('data-state'),
                    $this->elementsByValue($html, $part),
                ),
                $part,
            );
        }

        // what a form submits before the client has hydrated
        $inputs = $this->elementsByValue($html, 'data-radio-group-item-hidden-input');
        $this->assertSame(
            array_map(static fn(string $state): bool => $state === 'checked', $states),
            array_values(array_map(static fn($input): bool => $input->has('checked'), $inputs)),
        );
        foreach ($inputs as $input) {
            $this->assertSame('radio', $input->get('type'));
            $this->assertSame('plan', $input->get('name'));
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: list<bool>}>
     */
    public static function disabledItems(): iterable
    {
        yield 'disabled root' => ['disabled="{true}"', '', '', [true, true]];
        yield 'disabled item' => ['', 'disabled="{true}"', '', [true, false]];
        yield 'item enabled in a disabled root' => ['disabled="{true}"', '', 'disabled="{false}"', [true, false]];
    }

    /**
     * @param list<bool> $disabled
     */
    #[Test]
    #[DataProvider('disabledItems')]
    public function inheritsDisabledFromTheRootUnlessTheItemSaysOtherwise(
        string $root,
        string $first,
        string $second,
        array $disabled,
    ): void {
        $html = $this->renderTemplate(
            '
            <primitives:radioGroup.root ' .
            $root .
            '>
                <primitives:radioGroup.item value="option-1" ' .
            $first .
            '>
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemHiddenInput />
                </primitives:radioGroup.item>
                <primitives:radioGroup.item value="option-2" ' .
            $second .
            '>
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemHiddenInput />
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ',
        );

        foreach (['data-radio-group-item', 'data-radio-group-item-control'] as $part) {
            $this->assertSame(
                $disabled,
                array_values(array_map(
                    static fn($element): bool => $element->has('data-disabled'),
                    $this->elementsByValue($html, $part),
                )),
                $part,
            );
        }
        $this->assertSame(
            $disabled,
            array_values(array_map(
                static fn($input): bool => $input->has('disabled'),
                $this->elementsByValue($html, 'data-radio-group-item-hidden-input'),
            )),
        );
    }
}
