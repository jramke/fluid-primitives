<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class RadioGroupRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersRadiogroupRootWithRoleAndDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:radioGroup.root>
                <primitives:radioGroup.item value="option-1">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ');

        $this->assertStringContainsString('role="radiogroup"', $html);
        $this->assertStringContainsString('data-scope="radio-group"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersUncheckedStateWhenItemIsNotSelected(): void
    {
        $html = $this->renderTemplate('
            <primitives:radioGroup.root>
                <primitives:radioGroup.item value="option-1">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ');

        $this->assertStringContainsString('data-state="unchecked"', $html);
    }

    #[Test]
    public function rendersCheckedStateWhenItemMatchesDefaultValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:radioGroup.root defaultValue="option-2">
                <primitives:radioGroup.item value="option-1">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                </primitives:radioGroup.item>
                <primitives:radioGroup.item value="option-2">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 2</primitives:radioGroup.itemText>
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ');

        $this->assertStringContainsString('data-state="checked"', $html);
        $this->assertStringContainsString('data-state="unchecked"', $html);
    }

    #[Test]
    public function inheritsDisabledFromRootToAllItems(): void
    {
        $html = $this->renderTemplate('
            <primitives:radioGroup.root disabled="{true}">
                <primitives:radioGroup.item value="option-1">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 1</primitives:radioGroup.itemText>
                </primitives:radioGroup.item>
                <primitives:radioGroup.item value="option-2">
                    <primitives:radioGroup.itemControl />
                    <primitives:radioGroup.itemText>Option 2</primitives:radioGroup.itemText>
                </primitives:radioGroup.item>
            </primitives:radioGroup.root>
        ');

        $this->assertStringContainsString('aria-disabled="true"', $html);
        $this->assertGreaterThanOrEqual(2, preg_match_all('/data-disabled/', $html));
    }
}
