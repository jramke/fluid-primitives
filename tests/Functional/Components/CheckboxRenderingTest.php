<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class CheckboxRenderingTest extends FunctionalTestCase
{
    private const array PARTS = [
        'data-checkbox-root',
        'data-checkbox-control',
        'data-checkbox-indicator',
        'data-checkbox-label',
    ];

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool|string, 3: bool}>
     */
    public static function checkedStates(): iterable
    {
        yield 'unchecked by default' => ['', 'unchecked', false, false];
        yield 'checked' => ['defaultChecked="{true}"', 'checked', true, true];
        // a native checkbox has no attribute for it: the client sets its `indeterminate` property, and it is not checked
        yield 'indeterminate' => ['defaultChecked="indeterminate"', 'indeterminate', 'indeterminate', false];
    }

    #[Test]
    #[DataProvider('checkedStates')]
    public function rendersTheStateOnEveryPartAndShowsTheIndicatorUnlessUnchecked(
        string $arguments,
        string $state,
        bool|string $defaultChecked,
        bool $nativeChecked,
    ): void {
        $html = $this->renderTemplate('
            <primitives:checkbox.root ' . $arguments . '>
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
                <primitives:checkbox.label>Accept terms</primitives:checkbox.label>
                <primitives:checkbox.hiddenInput />
            </primitives:checkbox.root>
        ');

        // the label is what makes a click anywhere on the checkbox reach the native input inside it
        $root = $this->elementWith($html, 'data-checkbox-root');
        $this->assertSame('label', $root->tag);
        foreach (self::PARTS as $part) {
            $this->assertSame($state, $this->elementWith($html, $part)->get('data-state'), $part);
        }

        $this->assertSame($state === 'unchecked', $this->elementWith($html, 'data-checkbox-indicator')->has('hidden'));
        // what a form submits before the client has hydrated
        $this->assertSame($nativeChecked, $this->elementWith($html, 'data-checkbox-hidden-input')->has('checked'));

        $rootId = (string)$root->get('data-checkbox-root');
        $props = HydrationRegistry::getInstance()->get('primitives', 'checkbox', $rootId)['props'];
        $this->assertSame($defaultChecked, $props['defaultChecked']);
    }

    #[Test]
    public function carriesTheFlagsOnEveryPartAndOnTheNativeInput(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root defaultChecked="{true}" disabled="{true}" readOnly="{true}" invalid="{true}" required="{true}" name="accept" value="yes">
                <primitives:checkbox.control>
                    <primitives:checkbox.indicator>Check</primitives:checkbox.indicator>
                </primitives:checkbox.control>
                <primitives:checkbox.label>Accept terms</primitives:checkbox.label>
                <primitives:checkbox.hiddenInput />
            </primitives:checkbox.root>
        ');

        foreach (self::PARTS as $part) {
            $element = $this->elementWith($html, $part);
            foreach (['data-disabled', 'data-readonly', 'data-invalid', 'data-required'] as $flag) {
                $this->assertTrue($element->has($flag), sprintf('%s should carry %s.', $part, $flag));
            }
        }

        // what the browser itself enforces and what assistive technology reads off the native input
        $input = $this->elementWith($html, 'data-checkbox-hidden-input');
        $this->assertSame('checkbox', $input->get('type'));
        $this->assertTrue($input->has('disabled'));
        $this->assertTrue($input->has('required'));
        $this->assertSame('true', $input->get('aria-invalid'));
        $this->assertSame('accept', $input->get('name'));
        $this->assertSame('yes', $input->get('value'));
    }

    #[Test]
    public function rejectsADefaultCheckedThatIsNeitherABooleanNorIndeterminate(): void
    {
        $this->expectExceptionMessage(
            "The defaultChecked value 'maybe' is invalid. It must be a boolean or 'indeterminate'.",
        );

        $this->renderTemplate('
            <primitives:checkbox.root defaultChecked="maybe">
                <primitives:checkbox.control />
            </primitives:checkbox.root>
        ');
    }

    #[Test]
    public function shipsNameAndValueToTheClient(): void
    {
        $html = $this->renderTemplate('
            <primitives:checkbox.root name="accept" value="yes">
                <primitives:checkbox.control />
            </primitives:checkbox.root>
        ');

        $rootId = (string)$this->elementWith($html, 'data-checkbox-root')->get('data-checkbox-root');
        $props = HydrationRegistry::getInstance()->get('primitives', 'checkbox', $rootId)['props'];
        $this->assertSame('accept', $props['name']);
        $this->assertSame('yes', $props['value']);
    }
}
