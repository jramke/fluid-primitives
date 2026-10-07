<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
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

        $this->assertStringContainsString('data-field-root="', $html);
        $this->assertStringContainsString('data-name="notifications"', $html);

        foreach (['data-field-root', 'data-field-label', 'data-field-helper-text', 'data-field-error-text'] as $part) {
            $tag = $this->extractTag($html, $part);
            foreach (['data-invalid', 'data-disabled', 'data-readonly', 'data-required'] as $state) {
                $this->assertStringContainsString($state, $tag, sprintf('%s should carry %s.', $part, $state));
            }
        }
    }

    #[Test]
    public function hidesErrorTextUnlessInvalid(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email">
                <primitives:field.errorText>Invalid email address.</primitives:field.errorText>
            </primitives:field.root>
        ');

        $this->assertStringContainsString('hidden', $this->extractTag($html, 'data-field-error-text'));
    }

    #[Test]
    public function showsErrorTextWhenInvalidButKeepsOneNarrowedToAConstraintHidden(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email" invalid="{true}">
                <primitives:field.errorText>Invalid email address.</primitives:field.errorText>
                <primitives:field.errorText match="' .
        self::MATCH_VALUE_MISSING .
        '">Email is required.</primitives:field.errorText>
            </primitives:field.root>
        ');

        preg_match_all('/<div[^>]*data-field-error-text="[^"]*"[^>]*>/', $html, $matches);
        $this->assertCount(2, $matches[0]);
        [$unmatched, $matched] = $matches[0];

        $this->assertStringNotContainsString('hidden', $unmatched);
        $this->assertStringNotContainsString('data-value', $unmatched);

        // which constraint failed is only known once the browser validated, so it stays hidden on the server
        $this->assertStringContainsString('hidden', $matched);
        $this->assertStringContainsString('data-value="valueMissing"', $matched);
    }

    #[Test]
    public function rendersIndicatorsHiddenUnlessTheirStateFollowsFromProps(): void
    {
        $html = $this->renderTemplate(
            '
            <primitives:field.root name="email" required="{true}">
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
        ',
        );

        preg_match_all('/<span[^>]*data-field-indicator="[^"]*"[^>]*>/', $html, $matches);
        $this->assertCount(4, $matches[0]);
        [$required, $invalid, $valid, $validating] = $matches[0];

        $this->assertFalse($this->hasHiddenAttribute($required));
        $this->assertStringContainsString('data-type="required"', $required);
        $this->assertStringContainsString('data-value="required"', $required);
        $this->assertStringContainsString('aria-hidden="true"', $required);

        // valid and validating are results of validation, which only exist on the client
        foreach ([$invalid, $valid, $validating] as $indicator) {
            $this->assertTrue($this->hasHiddenAttribute($indicator));
        }
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

        $control = $this->extractTag($html, 'data-field-control');
        $this->assertStringContainsString('name="email"', $control);
        $this->assertStringContainsString('disabled', $control);
        $this->assertStringContainsString('required', $control);
        $this->assertStringContainsString('aria-invalid="true"', $control);
    }

    #[Test]
    public function shipsTheValidationModeToTheClientDefaultingToOnBlur(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email" rootId="default-mode"></primitives:field.root>
            <primitives:field.root name="name" rootId="own-mode" validationMode="{f:constant(name: \'Jramke\\FluidPrimitives\\Enum\\ValidationMode::OnChange\')}"></primitives:field.root>
        ');

        $this->assertStringContainsString('data-field-root="default-mode"', $html);
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

    #[Test]
    public function propagatesNameAndStateToNestedFieldAwareComponentAndOverridesItsIds(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="marketing" rootId="my-field" disabled="{true}">
                <primitives:field.label>Marketing emails</primitives:field.label>
                <primitives:switch.root>
                    <primitives:switch.control>
                        <primitives:switch.thumb />
                    </primitives:switch.control>
                    <primitives:switch.hiddenInput />
                </primitives:switch.root>
            </primitives:field.root>
        ');

        // Field.Label is marked with the Field's root id, independently of the nested Switch.
        $this->assertStringContainsString('data-field-label="my-field"', $html);

        // The Switch inherits the Field's name/disabled state...
        $this->assertStringContainsString('name="marketing"', $html);
        $this->assertStringContainsString('data-disabled', $html);

        // ...and its hiddenInput carries the Field's generated "control" id (per
        // FieldIdMapping::FIELD_ID_PARTS['switch']['control'] = 'hiddenInput') - this is what lets a
        // <label for="..."> pointing at the Field's control id reach the actual native input.
        $hiddenInputTag = $this->extractTag($html, 'data-switch-hidden-input');
        $this->assertStringContainsString('id="field:my-field:control"', $hiddenInputTag);
    }

    private function hasHiddenAttribute(string $tag): bool
    {
        return preg_match('/\shidden[\s>=]/', $tag) === 1;
    }

    private function extractTag(string $html, string $attribute): string
    {
        $matched = preg_match('/<[a-z]+[^>]*' . preg_quote($attribute, '/') . '="[^"]*"[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected exactly one element with %s.', $attribute));

        return $matches[0];
    }
}
