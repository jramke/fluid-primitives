<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CheckboxGroupRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersCheckboxgroupRootWithRoleAndDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root>
                <primitives:checkbox.root value="option-1">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $this->assertStringContainsString('role="group"', $html);
        $this->assertStringContainsString('data-checkbox-group-root="', $html);
    }

    #[Test]
    public function rendersUncheckedWhenNotInDefaultValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root>
                <primitives:checkbox.root value="option-1">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $this->assertStringContainsString('data-state="unchecked"', $html);
    }

    #[Test]
    public function rendersCheckedWhenValueIsInDefaultValueArray(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root defaultValue="{0: \'option-2\'}">
                <primitives:checkbox.root value="option-1">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                </primitives:checkbox.root>
                <primitives:checkbox.root value="option-2">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 2</primitives:checkbox.label>
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $this->assertStringContainsString('data-state="checked"', $html);
        $this->assertStringContainsString('data-state="unchecked"', $html);
    }

    #[Test]
    public function passesNameThroughUnchangedWithoutAppendingBrackets(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root name="a11yNeeds[]">
                <primitives:checkbox.root value="wheelchair">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Wheelchair</primitives:checkbox.label>
                    <primitives:checkbox.hiddenInput />
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $this->assertStringContainsString('name="a11yNeeds[]"', $html);
        $this->assertStringNotContainsString('name="a11yNeeds[][]"', $html);
    }

    #[Test]
    public function allowsMultipleCheckboxesToBeChecked(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkboxGroup.root defaultValue="{0: \'option-1\', 1: \'option-3\'}">
                <primitives:checkbox.root value="option-1">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 1</primitives:checkbox.label>
                </primitives:checkbox.root>
                <primitives:checkbox.root value="option-2">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 2</primitives:checkbox.label>
                </primitives:checkbox.root>
                <primitives:checkbox.root value="option-3">
                    <primitives:checkbox.control />
                    <primitives:checkbox.label>Option 3</primitives:checkbox.label>
                </primitives:checkbox.root>
            </primitives:checkboxGroup.root>
        ');

        $checkedCount = preg_match_all('/data-state="checked"/', $html);
        $uncheckedCount = preg_match_all('/data-state="unchecked"/', $html);

        $this->assertGreaterThanOrEqual(2, $checkedCount);
        $this->assertGreaterThanOrEqual(1, $uncheckedCount);
    }

    #[Test]
    public function checkboxesTakeTheirFieldStateFromTheGroupNotFromTheEnclosingField(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="needs[]" rootId="needs-field" required="{true}" disabled="{true}">
                <primitives:checkboxGroup.root>
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

        preg_match_all('/<input[^>]*data-checkbox-hidden-input="[^"]*"[^>]*>/', $html, $matches);
        $this->assertCount(2, $matches[0]);

        foreach ($matches[0] as $hiddenInput) {
            // "required" on every checkbox would mean "all of them must be checked"; the group means "at least one"
            $this->assertDoesNotMatchRegularExpression('/\srequired[\s>=]/', $hiddenInput);
            // the Field's control id belongs to the group, not to each of its checkboxes
            $this->assertStringNotContainsString('field:needs-field:control', $hiddenInput);
            // while the group still hands its own (Field-inherited) disabled state down
            $this->assertStringContainsString('disabled', $hiddenInput);
        }
        $this->assertStringContainsString('data-required', $this->extractGroupRoot($html));
    }

    private function extractGroupRoot(string $html): string
    {
        preg_match('/<[a-z]+[^>]*data-checkbox-group-root="[^"]*"[^>]*>/', $html, $matches);

        return $matches[0] ?? '';
    }
}
