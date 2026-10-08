<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class FieldsetRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersANativeFieldsetAndCarriesItsStateOnEveryPart(): void
    {
        $html = $this->renderTemplate('
            <primitives:fieldset.root disabled="{true}" invalid="{true}">
                <primitives:fieldset.legend>Address</primitives:fieldset.legend>
                <primitives:fieldset.helperText>Where we ship to.</primitives:fieldset.helperText>
                <primitives:fieldset.errorText>Check the address.</primitives:fieldset.errorText>
            </primitives:fieldset.root>
        ');

        $root = $this->extractTag($html, 'data-fieldset-root');
        $this->assertStringStartsWith('<fieldset', $root);
        $this->assertTrue($this->hasAttribute($root, 'disabled'));
        $this->assertStringContainsString('aria-invalid="true"', $root);

        foreach (['legend', 'helper-text', 'error-text', 'root'] as $part) {
            $tag = $this->extractTag($html, 'data-fieldset-' . $part);
            $this->assertTrue($this->hasAttribute($tag, 'data-disabled'), $part . ' should carry data-disabled.');
            $this->assertTrue($this->hasAttribute($tag, 'data-invalid'), $part . ' should carry data-invalid.');
        }

        // the machine resolves its elements by the ids it stamps itself on hydration
        $this->assertStringNotContainsString(' id=', $html);
    }

    #[Test]
    public function showsTheErrorTextOnlyWhileTheFieldsetIsInvalid(): void
    {
        $valid = $this->renderTemplate('
            <primitives:fieldset.root>
                <primitives:fieldset.errorText>Check the address.</primitives:fieldset.errorText>
            </primitives:fieldset.root>
        ');
        $invalid = $this->renderTemplate('
            <primitives:fieldset.root invalid="{true}">
                <primitives:fieldset.errorText>Check the address.</primitives:fieldset.errorText>
            </primitives:fieldset.root>
        ');

        $validTag = $this->extractTag($valid, 'data-fieldset-error-text');
        $invalidTag = $this->extractTag($invalid, 'data-fieldset-error-text');

        $this->assertTrue($this->hasAttribute($validTag, 'hidden'));
        $this->assertFalse($this->hasAttribute($invalidTag, 'hidden'));
        foreach ([$validTag, $invalidTag] as $tag) {
            $this->assertStringContainsString('aria-live="polite"', $tag);
        }
    }

    #[Test]
    public function rendersAFieldInsideADisabledFieldsetDisabledWithoutHydratingItAsDisabled(): void
    {
        $html = $this->renderTemplate('
            <primitives:fieldset.root disabled="{true}">
                <primitives:field.root name="street" rootId="in-disabled">
                    <primitives:input.root><primitives:input.input /></primitives:input.root>
                </primitives:field.root>
            </primitives:fieldset.root>
            <primitives:fieldset.root>
                <primitives:field.root name="city" rootId="in-enabled">
                    <primitives:input.root><primitives:input.input /></primitives:input.root>
                </primitives:field.root>
            </primitives:fieldset.root>
        ');

        $disabledField = $this->extractTag($html, 'data-field-root="in-disabled"');
        $enabledField = $this->extractTag($html, 'data-field-root="in-enabled"');
        $this->assertTrue($this->hasAttribute($disabledField, 'data-disabled'));
        $this->assertFalse($this->hasAttribute($enabledField, 'data-disabled'));

        $this->assertTrue($this->hasAttribute($this->extractTag($html, 'name="street"', 'input'), 'disabled'));
        $this->assertFalse($this->hasAttribute($this->extractTag($html, 'name="city"', 'input'), 'disabled'));

        // what the machine hydrates with is what was written: it learns about a fieldset from the DOM,
        // so enabling the fieldset later would otherwise leave this field disabled
        $this->assertArrayNotHasKey(
            'disabled',
            HydrationRegistry::getInstance()->get('primitives', 'field', 'in-disabled')['props'] ?? [],
        );
    }

    private function hasAttribute(string $tag, string $attribute): bool
    {
        return preg_match('/\s' . preg_quote($attribute, '/') . '[\s>=\/]/', $tag) === 1;
    }

    private function extractTag(string $html, string $attribute, string $tagName = '[a-z]+'): string
    {
        $matched = preg_match('/<' . $tagName . '[^>]*\s' . preg_quote($attribute, '/') . '[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected an element with %s.', $attribute));

        return $matches[0];
    }
}
