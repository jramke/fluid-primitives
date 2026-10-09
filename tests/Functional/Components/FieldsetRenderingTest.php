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

        $root = $this->elementWith($html, 'data-fieldset-root');
        $this->assertSame('fieldset', $root->tag);
        $this->assertTrue($root->has('disabled'));
        $this->assertSame('true', $root->get('aria-invalid'));

        foreach (['legend', 'helper-text', 'error-text', 'root'] as $part) {
            $element = $this->elementWith($html, 'data-fieldset-' . $part);
            $this->assertTrue($element->has('data-disabled'), $part . ' should carry data-disabled.');
            $this->assertTrue($element->has('data-invalid'), $part . ' should carry data-invalid.');
        }
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

        $validText = $this->elementWith($valid, 'data-fieldset-error-text');
        $invalidText = $this->elementWith($invalid, 'data-fieldset-error-text');

        $this->assertTrue($validText->has('hidden'));
        $this->assertFalse($invalidText->has('hidden'));
        // a live region has to exist before its content changes
        foreach ([$validText, $invalidText] as $errorText) {
            $this->assertSame('polite', $errorText->get('aria-live'));
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

        $fields = $this->elementsByRootId($html);
        $this->assertTrue($fields['in-disabled']->has('data-disabled'));
        $this->assertFalse($fields['in-enabled']->has('data-disabled'));

        $inputs = [];
        foreach ($this->elementsWith($html, 'data-input-input') as $input) {
            $inputs[$input->get('name')] = $input;
        }
        $this->assertTrue($inputs['street']->has('disabled'));
        $this->assertFalse($inputs['city']->has('disabled'));

        // what the machine hydrates with is what was written: it learns about a fieldset from the DOM,
        // so enabling the fieldset later would otherwise leave this field disabled
        $props = HydrationRegistry::getInstance()->get('primitives', 'field', 'in-disabled')['props'];
        $this->assertArrayNotHasKey('disabled', $props);
    }

    /**
     * @return array<string, \Jramke\FluidPrimitives\Tests\Helper\HtmlElement>
     */
    private function elementsByRootId(string $html): array
    {
        $fields = [];
        foreach ($this->elementsWith($html, 'data-field-root') as $field) {
            $fields[$field->get('data-field-root')] = $field;
        }

        return $fields;
    }
}
