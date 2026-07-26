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
        $this->assertStringContainsString('data-scope="checkbox-group"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
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
}
