<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ButtonRenderingTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: array<string, ?string>, 2: string}>
     */
    public static function states(): iterable
    {
        yield 'idle' => ['', ['aria-disabled' => null, 'disabled' => null, 'data-disabled' => null], 'idle'];
        yield 'disabled' => [
            'disabled="{true}"',
            ['aria-disabled' => null, 'disabled' => '', 'data-disabled' => ''],
            'disabled',
        ];
        yield 'loading' => [
            'loading="{true}"',
            ['aria-disabled' => null, 'disabled' => '', 'data-disabled' => '', 'data-loading' => ''],
            'loading',
        ];
        yield 'loading wins over disabled' => [
            'loading="{true}" disabled="{true}"',
            ['aria-disabled' => null, 'disabled' => '', 'data-disabled' => '', 'data-loading' => ''],
            'loading',
        ];
        yield 'disabled and focusable' => [
            'disabled="{true}" focusableWhenDisabled="{true}"',
            ['aria-disabled' => 'true', 'disabled' => null, 'data-disabled' => ''],
            'disabled',
        ];
        yield 'loading and focusable' => [
            'loading="{true}" focusableWhenDisabled="{true}"',
            ['aria-disabled' => 'true', 'disabled' => null, 'data-disabled' => '', 'data-loading' => ''],
            'loading',
        ];
        yield 'focusable but not disabled' => [
            'focusableWhenDisabled="{true}"',
            ['aria-disabled' => null, 'disabled' => null, 'data-disabled' => null],
            'idle',
        ];
    }

    /**
     * @param array<string, ?string> $expectedAttributes null means the attribute is absent
     * @param string $visibleIndicator exactly one indicator is shown: the state of the button
     */
    #[Test]
    #[DataProvider('states')]
    public function rendersTheDisabledAttributesAndTheIndicatorOfTheState(
        string $arguments,
        array $expectedAttributes,
        string $visibleIndicator,
    ): void {
        $indicator = static fn(string $state): string => (
            '<primitives:button.indicator state="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\ButtonIndicatorState::' .
            ucfirst($state) .
            '\')}">' .
            $state .
            '</primitives:button.indicator>'
        );
        $html = $this->renderTemplate(
            '<primitives:button ' .
            $arguments .
            '>' .
            $indicator('idle') .
            $indicator('loading') .
            $indicator('disabled') .
            '</primitives:button>',
        );

        $button = $this->elementWith($html, 'data-button-root');
        $this->assertSame('button', $button->tag);
        foreach ($expectedAttributes as $attribute => $expected) {
            $this->assertSame($expected, $button->get($attribute), $attribute);
            $this->assertSame($expected !== null, $button->has($attribute), $attribute);
        }
        $this->assertSame($visibleIndicator === 'loading', $button->has('data-loading'));

        $indicators = $this->elementsByValue($html, 'data-button-indicator');
        $this->assertSame(['idle', 'loading', 'disabled'], array_keys($indicators));
        foreach ($indicators as $state => $element) {
            $this->assertSame($state !== $visibleIndicator, $element->has('hidden'), $state);
        }
    }

    #[Test]
    public function submitsOnlyWhenTheTypeIsSetToSubmit(): void
    {
        $this->assertSame(
            'button',
            $this->elementWith(
                $this->renderTemplate('<primitives:button>Save</primitives:button>'),
                'data-button-root',
            )->get('type'),
        );
        $this->assertSame(
            'submit',
            $this->elementWith(
                $this->renderTemplate('<primitives:button type="submit">Save</primitives:button>'),
                'data-button-root',
            )->get('type'),
        );
    }

    #[Test]
    public function sendsTheStateToTheClientSoItCanBeUpdatedThere(): void
    {
        HydrationRegistry::getInstance()->clear();

        $this->renderTemplate('
            <primitives:button loading="{true}" focusableWhenDisabled="{true}">Save</primitives:button>
        ');

        $props = array_values(HydrationRegistry::getInstance()->getAll()['primitives']['button'])[0]['props'];
        $this->assertTrue($props['loading']);
        $this->assertTrue($props['focusableWhenDisabled']);
        $this->assertFalse($props['disabled']);
    }
}
