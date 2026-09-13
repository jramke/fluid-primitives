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

        $this->assertStringContainsString('data-scope="field"', $html);
        $this->assertStringContainsString('data-name="notifications"', $html);
        $this->assertStringContainsString('data-invalid', $html);
        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('data-readonly', $html);
        $this->assertStringContainsString('data-required', $html);
        $this->assertStringContainsString('data-part="label"', $html);
        $this->assertStringContainsString('data-part="description"', $html);
    }

    #[Test]
    public function hidesErrorUnlessInvalid(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email">
                <primitives:field.error>Invalid email address.</primitives:field.error>
            </primitives:field.root>
        ');

        $this->assertStringContainsString('hidden', $this->extractTag($html, 'error'));
    }

    #[Test]
    public function showsErrorWhenInvalid(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email" invalid="{true}">
                <primitives:field.error>Invalid email address.</primitives:field.error>
            </primitives:field.root>
        ');

        $this->assertStringNotContainsString('hidden', $this->extractTag($html, 'error'));
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

        // Field.Label generates its own id independently of the nested Switch.
        $this->assertStringContainsString('id="field:my-field:label"', $html);

        // The Switch inherits the Field's name/disabled state...
        $this->assertStringContainsString('name="marketing"', $html);
        $this->assertStringContainsString('data-disabled', $html);

        // ...and its hiddenInput's id is overridden to the Field's generated "control" id
        // (per ComponentPartIdUtility::FIELD_ID_PARTS['switch']['control'] = 'hiddenInput'), rather than
        // generating its own "switch:..." id - this is what lets a <label for="..."> pointing at the
        // Field's control id reach the actual native input.
        $hiddenInputTag = $this->extractTag($html, 'hidden-input');
        $this->assertStringContainsString('id="field:my-field:control"', $hiddenInputTag);
        $this->assertStringNotContainsString('id="switch:', $hiddenInputTag);
    }

    private function extractTag(string $html, string $part): string
    {
        $matched = preg_match('/<[a-z]+[^>]*data-part="' . preg_quote($part, '/') . '"[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected exactly one element with data-part="%s".', $part));

        return $matches[0];
    }
}
