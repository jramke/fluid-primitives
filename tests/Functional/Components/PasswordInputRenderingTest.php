<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class PasswordInputRenderingTest extends FunctionalTestCase
{
    private const string TEMPLATE = '
        <primitives:passwordInput.root %s>
            <primitives:passwordInput.label>Password</primitives:passwordInput.label>
            <primitives:passwordInput.control>
                <primitives:passwordInput.input />
                <primitives:passwordInput.visibilityTrigger>
                    <primitives:passwordInput.indicator state="{f:constant(name: \'Jramke\FluidPrimitives\Enum\PasswordInputIndicatorState::Hidden\')}">Show</primitives:passwordInput.indicator>
                    <primitives:passwordInput.indicator state="{f:constant(name: \'Jramke\FluidPrimitives\Enum\PasswordInputIndicatorState::Visible\')}">Hide</primitives:passwordInput.indicator>
                </primitives:passwordInput.visibilityTrigger>
            </primitives:passwordInput.control>
        </primitives:passwordInput.root>
    ';

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    public static function visibilityStates(): iterable
    {
        yield 'hidden by default' => ['', 'hidden', 'password', 'false', 'Show password'];
        yield 'visible' => ['defaultVisible="{true}"', 'visible', 'text', 'true', 'Hide password'];
    }

    #[Test]
    #[DataProvider('visibilityStates')]
    public function rendersTheVisibilityOnTheInputTriggerAndIndicators(
        string $arguments,
        string $state,
        string $inputType,
        string $expanded,
        string $triggerLabel,
    ): void {
        $html = $this->renderTemplate(sprintf(self::TEMPLATE, $arguments));

        $input = $this->elementWith($html, 'data-password-input-input');
        $this->assertSame($inputType, $input->get('type'));
        $this->assertSame($state, $input->get('data-state'));

        $trigger = $this->elementWith($html, 'data-password-input-visibility-trigger');
        $this->assertSame($expanded, $trigger->get('aria-expanded'));
        $this->assertSame($triggerLabel, $trigger->get('aria-label'));
        $this->assertSame($state, $trigger->get('data-state'));

        $indicators = $this->elementsByValue($html, 'data-password-input-indicator');
        foreach (['visible', 'hidden'] as $indicatorState) {
            $this->assertSame(
                $indicatorState !== $state,
                $indicators[$indicatorState]->has('hidden'),
                sprintf('the %s indicator', $indicatorState),
            );
        }
    }

    #[Test]
    public function carriesTheFlagsOnEveryPartAndOnTheNativeInput(): void
    {
        $html = $this->renderTemplate(sprintf(
            self::TEMPLATE,
            'name="password" disabled="{true}" readOnly="{true}" invalid="{true}" required="{true}" autoComplete="{f:constant(name: \'Jramke\FluidPrimitives\Enum\PasswordAutoComplete::NewPassword\')}"',
        ));

        foreach (['root', 'label', 'control', 'input'] as $part) {
            $element = $this->elementWith($html, "data-password-input-{$part}");
            foreach (['data-disabled', 'data-readonly', 'data-invalid'] as $flag) {
                $this->assertTrue($element->has($flag), sprintf('%s should carry %s.', $part, $flag));
            }
        }
        $this->assertTrue($this->elementWith($html, 'data-password-input-label')->has('data-required'));

        $input = $this->elementWith($html, 'data-password-input-input');
        $this->assertSame('password', $input->get('name'));
        $this->assertSame('new-password', $input->get('autocomplete'));
        $this->assertSame('true', $input->get('aria-invalid'));
        foreach (['disabled', 'readonly', 'required'] as $attribute) {
            $this->assertTrue($input->has($attribute), $attribute);
        }
        $this->assertFalse($input->has('data-1p-ignore'));

        $this->assertTrue($this->elementWith($html, 'data-password-input-visibility-trigger')->has('disabled'));
    }

    #[Test]
    public function keepsPasswordManagersAwayOnlyWhenAsked(): void
    {
        $html = $this->renderTemplate(sprintf(self::TEMPLATE, 'ignorePasswordManagers="{true}"'));

        $input = $this->elementWith($html, 'data-password-input-input');
        $this->assertTrue($input->has('data-1p-ignore'));
        $this->assertSame('true', $input->get('data-lpignore'));
        $this->assertSame('other', $input->get('data-form-type'));
    }

    #[Test]
    public function takesTheFieldControlIdAndName(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="password" rootId="my-field">
                <primitives:field.label>Password</primitives:field.label>
                <primitives:passwordInput.root>
                    <primitives:passwordInput.input />
                </primitives:passwordInput.root>
            </primitives:field.root>
        ');

        $input = $this->elementWith($html, 'data-password-input-input');
        $this->assertSame('password', $input->get('name'));
        // the id the Field's label points at, so the label names the input before the client hydrates
        $this->assertSame('field:my-field:control', $input->get('id'));
    }
}
