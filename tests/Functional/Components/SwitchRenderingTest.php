<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SwitchRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersRootAsLabelWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root>
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
                <primitives:switch.label>Enable notifications</primitives:switch.label>
            </primitives:switch.root>
        ');

        $this->assertStringContainsString('<label', $html);
        $this->assertStringContainsString('data-scope="switch"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
    }

    #[Test]
    public function rendersUncheckedStateByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root>
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
                <primitives:switch.hiddenInput />
            </primitives:switch.root>
        ');

        $this->assertStringContainsString('data-state="unchecked"', $html);
        $this->assertDoesNotMatchRegularExpression('/<input[^>]* checked/', $html);
    }

    #[Test]
    public function rendersCheckedStateWhenDefaultCheckedIsTrue(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root defaultChecked="{true}">
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
                <primitives:switch.hiddenInput />
            </primitives:switch.root>
        ');

        $this->assertStringContainsString('data-state="checked"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*checked/', $html);
    }

    #[Test]
    public function includesDisabledReadonlyInvalidRequiredAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root disabled="{true}" readOnly="{true}" invalid="{true}" required="{true}">
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
            </primitives:switch.root>
        ');

        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('data-readonly', $html);
        $this->assertStringContainsString('data-invalid', $html);
        $this->assertStringContainsString('data-required', $html);
    }

    #[Test]
    public function hiddenInputReflectsNameAndValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root name="marketing" value="opt-in" defaultChecked="{true}">
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
                <primitives:switch.hiddenInput />
            </primitives:switch.root>
        ');

        $this->assertStringContainsString('name="marketing"', $html);
        $this->assertStringContainsString('value="opt-in"', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
    }

    #[Test]
    public function registersComponentWithPropsInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:switch.root defaultChecked="{true}" name="marketing">
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
            </primitives:switch.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];

        $this->assertArrayHasKey('switch', $hydrationData);
        $switchData = array_values($hydrationData['switch'])[0];
        $this->assertTrue($switchData['props']['defaultChecked']);
        $this->assertSame('marketing', $switchData['props']['name']);
    }
}
