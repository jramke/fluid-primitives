<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class FieldRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersRootLabelAndDescriptionWithRefsAndDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="notifications" invalid="{true}" disabled="{true}" readOnly="{true}" required="{true}">
                <primitives:field.label>Notifications</primitives:field.label>
                <primitives:field.description>Choose how you want to be notified.</primitives:field.description>
            </primitives:field.root>
        ');

        $this->assertStringContainsString('data-field-root="', $html);
        $this->assertStringContainsString('data-name="notifications"', $html);
        $this->assertStringContainsString('data-invalid', $html);
        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('data-readonly', $html);
        $this->assertStringContainsString('data-required', $html);
        $this->assertStringContainsString('data-field-label="', $html);
        $this->assertStringContainsString('data-field-description="', $html);
    }

    #[Test]
    public function hidesErrorUnlessInvalid(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email">
                <primitives:field.error>Invalid email address.</primitives:field.error>
            </primitives:field.root>
        ');

        $this->assertStringContainsString('hidden', $this->extractTag($html, 'data-field-error'));
    }

    #[Test]
    public function showsErrorWhenInvalid(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email" invalid="{true}">
                <primitives:field.error>Invalid email address.</primitives:field.error>
            </primitives:field.root>
        ');

        $this->assertStringNotContainsString('hidden', $this->extractTag($html, 'data-field-error'));
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

    private function extractTag(string $html, string $attribute): string
    {
        $matched = preg_match('/<[a-z]+[^>]*' . preg_quote($attribute, '/') . '="[^"]*"[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected exactly one element with %s.', $attribute));

        return $matches[0];
    }
}
