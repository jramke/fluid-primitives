<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class CheckboxGroupRenderingTest extends FunctionalTestCase
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
    public function rendersTheGroupRoleAndItsFlagsOnTheRoot(string $arguments, bool $flagged): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root ' . $arguments . '>
                <primitives:checkbox.root value="option-1">
                    <primitives:checkbox.control />
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        // the checkboxes find the group they belong to by this marker
        $group = $this->elementWith($html, 'data-checkbox-group-root');
        $this->assertSame('group', $group->get('role'));

        $expected = $flagged ? 'true' : 'false';
        $this->assertSame($expected, $group->get('aria-required'));
        $this->assertSame($expected, $group->get('aria-disabled'));
        $this->assertSame($expected, $group->get('aria-readonly'));
        foreach (['data-required', 'data-disabled', 'data-readonly', 'data-invalid'] as $flag) {
            $this->assertSame($flagged, $group->has($flag), $flag);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function defaultValues(): iterable
    {
        yield 'nothing selected' => ['', ['unchecked', 'unchecked', 'unchecked']];
        yield 'one value' => ['defaultValue="{0: \'option-2\'}"', ['unchecked', 'checked', 'unchecked']];
        yield 'several values' => [
            'defaultValue="{0: \'option-1\', 1: \'option-3\'}"',
            ['checked', 'unchecked', 'checked'],
        ];
    }

    /**
     * @param list<string> $states
     */
    #[Test]
    #[DataProvider('defaultValues')]
    public function checksEachCheckboxWhoseValueIsInTheDefaultValue(string $arguments, array $states): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root ' . $arguments . '>
                <primitives:checkbox.root value="option-1">
                    <primitives:checkbox.control />
                    <primitives:checkbox.hiddenInput />
                </primitives:checkbox.root>
                <primitives:checkbox.root value="option-2">
                    <primitives:checkbox.control />
                    <primitives:checkbox.hiddenInput />
                </primitives:checkbox.root>
                <primitives:checkbox.root value="option-3">
                    <primitives:checkbox.control />
                    <primitives:checkbox.hiddenInput />
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $roots = $this->elementsWith($html, 'data-checkbox-root');
        $this->assertSame($states, array_map(static fn($root): ?string => $root->get('data-state'), $roots));

        // the native inputs a form submits before the client has hydrated, one per checkbox in the same order
        $inputs = $this->elementsWith($html, 'data-checkbox-hidden-input');
        $this->assertSame(
            array_map(static fn(string $state): bool => $state === 'checked', $states),
            array_map(static fn($input): bool => $input->has('checked'), $inputs),
        );
        $this->assertSame(
            ['option-1', 'option-2', 'option-3'],
            array_map(static fn($input): ?string => $input->get('value'), $inputs),
        );
    }

    #[Test]
    public function passesNameThroughUnchangedWithoutAppendingBrackets(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root name="a11yNeeds[]">
                <primitives:checkbox.root value="wheelchair">
                    <primitives:checkbox.control />
                    <primitives:checkbox.hiddenInput />
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $this->assertSame('a11yNeeds[]', $this->elementWith($html, 'data-checkbox-hidden-input')->get('name'));
    }

    /**
     * @return iterable<string, array{0: string, 1: list<bool>}>
     */
    public static function maxSelectedValues(): iterable
    {
        yield 'at the maximum' => ['defaultValue="{0: \'a\'}" maxSelectedValues="1"', [false, true, true]];
        yield 'below the maximum' => ['defaultValue="{0: \'a\'}" maxSelectedValues="2"', [false, false, false]];
        yield 'no maximum' => ['defaultValue="{0: \'a\'}"', [false, false, false]];
    }

    /**
     * @param list<bool> $disabled
     */
    #[Test]
    #[DataProvider('maxSelectedValues')]
    public function disablesTheUncheckedCheckboxesOnceTheMaximumIsReached(string $arguments, array $disabled): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root ' . $arguments . '>
                <primitives:checkbox.root value="a"><primitives:checkbox.control /><primitives:checkbox.hiddenInput /></primitives:checkbox.root>
                <primitives:checkbox.root value="b"><primitives:checkbox.control /><primitives:checkbox.hiddenInput /></primitives:checkbox.root>
                <primitives:checkbox.root value="c"><primitives:checkbox.control /><primitives:checkbox.hiddenInput /></primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $inputs = $this->elementsWith($html, 'data-checkbox-hidden-input');
        $this->assertSame($disabled, array_map(static fn($input): bool => $input->has('disabled'), $inputs));
        $roots = $this->elementsWith($html, 'data-checkbox-root');
        $this->assertSame($disabled, array_map(static fn($root): bool => $root->has('data-disabled'), $roots));
    }

    #[Test]
    public function takesTheFieldStateFromTheGroupNotFromTheEnclosingField(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="needs[]" rootId="needs-field" required="{true}" disabled="{true}">
                <primitives:checkboxGroup.root>
                    <primitives:checkboxGroup.label>Needs</primitives:checkboxGroup.label>
                    <primitives:checkbox.root value="a">
                        <primitives:checkbox.control />
                        <primitives:checkbox.hiddenInput />
                    </primitives:checkbox.root>
                    <primitives:checkbox.root value="b">
                        <primitives:checkbox.control />
                        <primitives:checkbox.hiddenInput />
                    </primitives:checkbox.root>
                </primitives:checkboxGroup.root>
            </primitives:field.root>
        ');

        $inputs = $this->elementsWith($html, 'data-checkbox-hidden-input');
        $this->assertCount(2, $inputs);
        foreach ($inputs as $input) {
            // "required" on every checkbox would mean "all of them must be checked"; the group means "at least one"
            $this->assertFalse($input->has('required'));
            // the Field's control id belongs to the group, not to each of its checkboxes
            $this->assertNull($input->get('id'));
            // while the group still hands its own (Field-inherited) name and disabled state down
            $this->assertTrue($input->has('disabled'));
            $this->assertSame('needs[]', $input->get('name'));
        }

        $group = $this->elementWith($html, 'data-checkbox-group-root');
        $this->assertTrue($group->has('data-required'));
        $this->assertTrue($group->has('data-disabled'));
        $this->assertSame('field:needs-field:label', $this->elementWith($html, 'data-checkbox-group-label')->get('id'));

        $groupId = (string)$group->get('data-checkbox-group-root');
        $ids = HydrationRegistry::getInstance()->get('primitives', 'checkbox-group', $groupId)['props']['ids'];
        $this->assertSame(['control' => 'field:needs-field:control', 'label' => 'field:needs-field:label'], $ids);
    }
}
