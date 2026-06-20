<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class CheckboxRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersCheckboxRootAsLabelWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root>
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
                <primitives:checkbox.label>Accept terms</primitives:checkbox.label>
            </primitives:checkbox.root>
        ');

        $this->assertStringContainsString('<label', $html);
        $this->assertStringContainsString('data-scope="checkbox"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersUncheckedStateByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root>
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
            </primitives:checkbox.root>
        ');

        $this->assertStringContainsString('data-state="unchecked"', $html);
        $this->assertMatchesRegularExpression('/data-part="indicator".*hidden|hidden.*data-part="indicator"/s', $html);
    }

    #[Test]
    public function rendersCheckedStateWhenDefaultCheckedIsTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root defaultChecked="{true}">
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
            </primitives:checkbox.root>
        ');

        $this->assertStringContainsString('data-state="checked"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-part="indicator"[^>]*hidden/', $html);
    }

    #[Test]
    public function rendersIndeterminateState(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root defaultChecked="indeterminate">
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
            </primitives:checkbox.root>
        ');

        $this->assertStringContainsString('data-state="indeterminate"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-part="indicator"[^>]*hidden/', $html);
    }

    #[Test]
    public function includesDisabledReadonlyInvalidRequiredAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root defaultChecked="{true}" disabled="{true}" readOnly="{true}" invalid="{true}" required="{true}">
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
            </primitives:checkbox.root>
        ');

        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('data-readonly', $html);
        $this->assertStringContainsString('data-invalid', $html);
        $this->assertStringContainsString('data-required', $html);
    }

    #[Test]
    public function registersComponentWithPropsInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:checkbox.root defaultChecked="{true}" name="accept" value="yes">
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
            </primitives:checkbox.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayHasKey('checkbox', $hydrationData);
        $checkboxData = array_values($hydrationData['checkbox'])[0];
        $this->assertArrayHasKey('defaultChecked', $checkboxData['props']);
        $this->assertTrue($checkboxData['props']['defaultChecked']);
        $this->assertArrayHasKey('name', $checkboxData['props']);
        $this->assertSame('accept', $checkboxData['props']['name']);
    }
}
