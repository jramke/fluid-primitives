<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class FieldRenderingTest extends FunctionalTestCase
{
    private const string INDICATOR_REQUIRED = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\FieldIndicatorType::Required')}";
    private const string INDICATOR_INVALID = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\FieldIndicatorType::Invalid')}";
    private const string INDICATOR_VALID = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\FieldIndicatorType::Valid')}";
    private const string INDICATOR_VALIDATING = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\FieldIndicatorType::Validating')}";
    private const string MATCH_VALUE_MISSING = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\ValidityMatch::ValueMissing')}";

    #[Test]
    public function rendersEveryPartWithItsRefAndTheStateDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="notifications" invalid="{true}" disabled="{true}" readOnly="{true}" required="{true}">
                <primitives:field.label>Notifications</primitives:field.label>
                <primitives:field.helperText>Choose how you want to be notified.</primitives:field.helperText>
                <primitives:field.errorText>Pick one.</primitives:field.errorText>
            </primitives:field.root>
        ');

        $this->assertSame('notifications', $this->elementWith($html, 'data-field-root')->get('data-name'));

        foreach (['data-field-root', 'data-field-label', 'data-field-helper-text', 'data-field-error-text'] as $part) {
            $element = $this->elementWith($html, $part);
            foreach (['data-invalid', 'data-disabled', 'data-readonly', 'data-required'] as $state) {
                $this->assertTrue($element->has($state), sprintf('%s should carry %s.', $part, $state));
            }
        }
    }

    #[Test]
    public function showsAnErrorTextOnlyWhileTheFieldIsInvalidAndKeepsOneNarrowedToAConstraintHidden(): void
    {
        $valid = $this->renderTemplate('
            <primitives:field.root name="email">
                <primitives:field.errorText>Invalid email address.</primitives:field.errorText>
            </primitives:field.root>
        ');
        $this->assertTrue($this->elementWith($valid, 'data-field-error-text')->has('hidden'));

        $invalid = $this->renderTemplate('
            <primitives:field.root name="email" invalid="{true}">
                <primitives:field.errorText>Invalid email address.</primitives:field.errorText>
                <primitives:field.errorText match="' .
        self::MATCH_VALUE_MISSING .
        '">Email is required.</primitives:field.errorText>
            </primitives:field.root>
        ');

        $errorTexts = $this->elementsWith($invalid, 'data-field-error-text');
        $this->assertCount(2, $errorTexts);
        [$unmatched, $matched] = $errorTexts;

        $this->assertFalse($unmatched->has('hidden'));
        $this->assertFalse($unmatched->has('data-value'));
        // which constraint failed is only known once the browser validated, so it stays hidden on the server
        $this->assertTrue($matched->has('hidden'));
        $this->assertSame('valueMissing', $matched->get('data-value'));
    }

    #[Test]
    public function rendersIndicatorsHiddenUnlessTheirStateFollowsFromProps(): void
    {
        $field = static fn(string $arguments): string => (
            '
            <primitives:field.root name="email" ' .
            $arguments .
            '>
                <primitives:field.indicator type="' .
            self::INDICATOR_REQUIRED .
            '">*</primitives:field.indicator>
                <primitives:field.indicator type="' .
            self::INDICATOR_INVALID .
            '">!</primitives:field.indicator>
                <primitives:field.indicator type="' .
            self::INDICATOR_VALID .
            '">ok</primitives:field.indicator>
                <primitives:field.indicator type="' .
            self::INDICATOR_VALIDATING .
            '">...</primitives:field.indicator>
            </primitives:field.root>
        '
        );
        $hidden = fn(string $html): array => array_map(
            static fn($indicator): bool => $indicator->has('hidden'),
            $this->elementsByValue($html, 'data-field-indicator'),
        );

        // valid and validating are results of validation, which only exist on the client
        $required = $this->renderTemplate($field('required="{true}"'));
        $this->assertSame(
            ['required' => false, 'invalid' => true, 'valid' => true, 'validating' => true],
            $hidden($required),
        );
        $indicator = $this->elementsByValue($required, 'data-field-indicator')['required'];
        $this->assertSame('required', $indicator->get('data-type'));
        $this->assertSame('true', $indicator->get('aria-hidden'));

        $invalid = $this->renderTemplate($field('invalid="{true}"'));
        $this->assertSame(
            ['required' => true, 'invalid' => false, 'valid' => true, 'validating' => true],
            $hidden($invalid),
        );
    }

    #[Test]
    public function rendersTheStateOnABareControlSoItIsRightBeforeHydration(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email" disabled="{true}" required="{true}" invalid="{true}">
                <primitives:field.control asChild="{true}">
                    <input type="email" />
                </primitives:field.control>
            </primitives:field.root>
        ');

        $control = $this->elementWith($html, 'data-field-control');
        $this->assertSame('email', $control->get('name'));
        // native attributes, not the data-* ones they would otherwise be mistaken for
        $this->assertTrue($control->has('disabled'));
        $this->assertTrue($control->has('required'));
        $this->assertSame('true', $control->get('aria-invalid'));
    }

    #[Test]
    public function shipsTheValidationModeToTheClientDefaultingToOnBlur(): void
    {
        $this->renderTemplate('
            <primitives:field.root name="email" rootId="default-mode"></primitives:field.root>
            <primitives:field.root name="name" rootId="own-mode" validationMode="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\ValidationMode::OnChange\')}"></primitives:field.root>
        ');

        $registry = HydrationRegistry::getInstance();
        $this->assertSame(
            'onBlur',
            $registry->get('primitives', 'field', 'default-mode')['props']['validationMode'] ?? null,
        );
        $this->assertSame(
            'onChange',
            $registry->get('primitives', 'field', 'own-mode')['props']['validationMode'] ?? null,
        );
    }

    #[Test]
    public function requiresAsChildOnControl(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("'asChild' prop is required");

        $this->renderTemplate('
            <primitives:field.root name="email">
                <primitives:field.control>
                    <input type="email" />
                </primitives:field.control>
            </primitives:field.root>
        ');
    }

    /**
     * The components a Field can wrap (Classes/Utility/FieldIdMapping.php): the markup of the component, the marker of the
     * native control it hands the Field's control id to, the marker of its own label and the one of the element that
     * submits the name (the control itself, unless the component submits through hidden inputs of its own).
     *
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function fieldAwareComponents(): iterable
    {
        yield 'an input' => [
            '<primitives:input.root>
                <primitives:input.label>Label</primitives:input.label>
                <primitives:input.input />
            </primitives:input.root>',
            'data-input-input',
            'data-input-label',
            'data-input-input',
        ];
        yield 'a textarea' => [
            '<primitives:textarea.root>
                <primitives:textarea.label>Label</primitives:textarea.label>
                <primitives:textarea.textarea />
            </primitives:textarea.root>',
            'data-textarea-textarea',
            'data-textarea-label',
            'data-textarea-textarea',
        ];
        yield 'a number input' => [
            '<primitives:numberInput.root>
                <primitives:numberInput.label>Label</primitives:numberInput.label>
                <primitives:numberInput.control>
                    <primitives:numberInput.input />
                </primitives:numberInput.control>
            </primitives:numberInput.root>',
            'data-number-input-input',
            'data-number-input-label',
            'data-number-input-input',
        ];
        yield 'a switch' => [
            '<primitives:switch.root>
                <primitives:switch.control><primitives:switch.thumb /></primitives:switch.control>
                <primitives:switch.label>Label</primitives:switch.label>
                <primitives:switch.hiddenInput />
            </primitives:switch.root>',
            'data-switch-hidden-input',
            'data-switch-label',
            'data-switch-hidden-input',
        ];
        yield 'a checkbox' => [
            '<primitives:checkbox.root>
                <primitives:checkbox.control />
                <primitives:checkbox.label>Label</primitives:checkbox.label>
                <primitives:checkbox.hiddenInput />
            </primitives:checkbox.root>',
            'data-checkbox-hidden-input',
            'data-checkbox-label',
            'data-checkbox-hidden-input',
        ];
        yield 'a select' => [
            '<primitives:select.root>
                <primitives:select.label>Label</primitives:select.label>
                <primitives:select.control>
                    <primitives:select.trigger>Select</primitives:select.trigger>
                </primitives:select.control>
                <primitives:select.hiddenSelect />
            </primitives:select.root>',
            'data-select-hidden-select',
            'data-select-label',
            'data-select-hidden-select',
        ];
        yield 'a combobox' => [
            '<primitives:combobox.root>
                <primitives:combobox.label>Label</primitives:combobox.label>
                <primitives:combobox.control>
                    <primitives:combobox.input />
                </primitives:combobox.control>
                <primitives:combobox.hiddenInput />
            </primitives:combobox.root>',
            'data-combobox-input',
            'data-combobox-label',
            'data-combobox-hidden-input',
        ];
        yield 'a file upload' => [
            '<primitives:fileUpload.root>
                <primitives:fileUpload.label>Label</primitives:fileUpload.label>
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>',
            'data-file-upload-hidden-input',
            'data-file-upload-label',
            'data-file-upload-hidden-input',
        ];
    }

    #[Test]
    #[DataProvider('fieldAwareComponents')]
    public function handsTheNameTheStateAndTheIdsOfTheFieldToTheNativeControlOfAFieldAwareComponent(
        string $component,
        string $controlMarker,
        string $labelMarker,
        string $nameMarker,
    ): void {
        $html = $this->renderTemplate('
            <primitives:field.root name="marketing" rootId="my-field" disabled="{true}" required="{true}">
                <primitives:field.label>Field label</primitives:field.label>' .
        $component .
        '
            </primitives:field.root>
        ');

        // Field.Label is marked with the Field's root id, independently of the nested component
        $this->assertSame('my-field', $this->elementWith($html, 'data-field-label')->get('data-field-label'));

        $control = $this->elementWith($html, $controlMarker);
        $this->assertTrue($control->has('disabled'));
        $this->assertTrue($control->has('required'));
        $this->assertSame('marketing', $this->elementWith($html, $nameMarker)->get('name'));
        // the ids a <label for="..."> of the Field points at: the client machine looks its control up by it
        // and a rename of a FieldArray row restamps them by the `field:<rootId>:` prefix
        $this->assertSame('field:my-field:control', $control->get('id'));
        $this->assertSame('field:my-field:label', $this->elementWith($html, $labelMarker)->get('id'));
    }
}
