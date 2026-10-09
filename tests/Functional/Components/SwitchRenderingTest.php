<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class SwitchRenderingTest extends FunctionalTestCase
{
    private const array PARTS = ['data-switch-root', 'data-switch-control', 'data-switch-thumb', 'data-switch-label'];

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function checkedStates(): iterable
    {
        yield 'unchecked by default' => ['', 'unchecked', false];
        yield 'checked' => ['defaultChecked="{true}"', 'checked', true];
    }

    #[Test]
    #[DataProvider('checkedStates')]
    public function rendersTheStateOnEveryPartAndOnTheNativeInput(string $arguments, string $state, bool $checked): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root ' . $arguments . '>
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
                <primitives:switch.label>Enable notifications</primitives:switch.label>
                <primitives:switch.hiddenInput />
            </primitives:switch.root>
        ');

        // the label is what makes a click anywhere on the switch reach the native input inside it
        $root = $this->elementWith($html, 'data-switch-root');
        $this->assertSame('label', $root->tag);
        foreach (self::PARTS as $part) {
            $this->assertSame($state, $this->elementWith($html, $part)->get('data-state'), $part);
        }

        // what a form submits before the client has hydrated
        $this->assertSame($checked, $this->elementWith($html, 'data-switch-hidden-input')->has('checked'));

        $rootId = (string)$root->get('data-switch-root');
        $this->assertSame(
            $checked,
            HydrationRegistry::getInstance()->get('primitives', 'switch', $rootId)['props']['defaultChecked'],
        );
    }

    #[Test]
    public function carriesTheFlagsOnEveryPartAndOnTheNativeInput(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root name="marketing" value="opt-in" disabled="{true}" readOnly="{true}" invalid="{true}" required="{true}">
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
                <primitives:switch.label>Enable notifications</primitives:switch.label>
                <primitives:switch.hiddenInput />
            </primitives:switch.root>
        ');

        foreach (self::PARTS as $part) {
            $element = $this->elementWith($html, $part);
            foreach (['data-disabled', 'data-readonly', 'data-invalid', 'data-required'] as $flag) {
                $this->assertTrue($element->has($flag), sprintf('%s should carry %s.', $part, $flag));
            }
        }

        // what the browser itself enforces and what assistive technology reads off the native input
        $input = $this->elementWith($html, 'data-switch-hidden-input');
        $this->assertSame('checkbox', $input->get('type'));
        $this->assertTrue($input->has('disabled'));
        $this->assertTrue($input->has('required'));
        $this->assertSame('true', $input->get('aria-invalid'));
        $this->assertSame('marketing', $input->get('name'));
        $this->assertSame('opt-in', $input->get('value'));
    }

    #[Test]
    public function shipsNameAndValueToTheClient(): void
    {
        $html = $this->renderTemplate('
            <primitives:switch.root name="marketing" value="opt-in">
                <primitives:switch.control>
                    <primitives:switch.thumb />
                </primitives:switch.control>
            </primitives:switch.root>
        ');

        $rootId = (string)$this->elementWith($html, 'data-switch-root')->get('data-switch-root');
        $props = HydrationRegistry::getInstance()->get('primitives', 'switch', $rootId)['props'];
        $this->assertSame('marketing', $props['name']);
        $this->assertSame('opt-in', $props['value']);
    }
}
