<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class InputRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HydrationRegistry::getInstance()->clear();
    }

    #[Test]
    public function rendersRootAndNativeInputWithRefsAndType(): void
    {
        $html = $this->renderTemplate('
            <primitives:input.root type="email" defaultValue="joost@example.com">
                <primitives:input.input />
            </primitives:input.root>
        ');

        $this->assertStringContainsString('data-scope="input"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
        $this->assertStringContainsString('data-part="input"', $html);
        $this->assertStringContainsString('type="email"', $html);
        $this->assertStringContainsString('value="joost@example.com"', $html);
    }

    #[Test]
    public function rendersPatternAndInputModeServerSideWhenSet(): void
    {
        // `pattern` is passed as a bound variable rather than an inline literal - a literal
        // containing `{3}`/`{4}` would be misparsed by Fluid as embedded object-accessor
        // expressions, same as any other string argument value with literal curly braces.
        $html = $this->renderTemplate('
            <primitives:input.root type="tel" pattern="{pattern}" inputMode="numeric">
                <primitives:input.input />
            </primitives:input.root>
        ', ['pattern' => '[0-9]{3}-[0-9]{4}']);

        $this->assertStringContainsString('pattern="[0-9]{3}-[0-9]{4}"', $html);
        $this->assertStringContainsString('inputmode="numeric"', $html);
    }

    #[Test]
    public function rendersPlaceholderAndAutocompleteServerSideWhenSet(): void
    {
        $html = $this->renderTemplate('
            <primitives:input.root type="email" placeholder="you@example.com" autocomplete="email">
                <primitives:input.input />
            </primitives:input.root>
        ');

        $this->assertStringContainsString('placeholder="you@example.com"', $html);
        $this->assertStringContainsString('autocomplete="email"', $html);
    }

    #[Test]
    public function rendersWordCountTextServerSideWhenMaxLengthIsSet(): void
    {
        $html = $this->renderTemplate('
            <primitives:input.root defaultValue="hello" maxLength="10">
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ');

        $this->assertStringContainsString('maxlength="10"', $html);
        $this->assertStringContainsString('>5 / 10 characters<', $html);
    }

    #[Test]
    public function omitsWordCountTextWithoutMaxLength(): void
    {
        $html = $this->renderTemplate('
            <primitives:input.root defaultValue="hello">
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ');

        $this->assertStringNotContainsString('characters', $html);
    }

    #[Test]
    public function omitsWordCountTextWhenTranslationDisabled(): void
    {
        $html = $this->renderTemplate('
            <primitives:input.root defaultValue="hello" maxLength="10" translations="{wordCount: false}">
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ');

        $this->assertStringNotContainsString('characters', $html);
    }

    #[Test]
    public function rendersGermanWordCountWhenLocaleIsGerman(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:input.root defaultValue="hallo" maxLength="10">
                <primitives:input.input />
                <primitives:input.wordCount />
            </primitives:input.root>
        ');

        $this->assertStringContainsString('>5 / 10 Zeichen<', $html);
    }

    #[Test]
    public function includesMergedTranslationsInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:input.root maxLength="10" translations="{wordCount: \'%count% of %max%\'}">
                <primitives:input.input />
            </primitives:input.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $inputData = array_values($hydrationData['input'])[0];

        $this->assertSame(['wordCount' => '%count% of %max%'], $inputData['props']['translations']);
    }

    #[Test]
    public function includesAnnounceDebounceDefaultInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:input.root>
                <primitives:input.input />
            </primitives:input.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $inputData = array_values($hydrationData['input'])[0];

        $this->assertSame(600, $inputData['props']['announceDebounce']);
    }

    #[Test]
    public function propagatesNameAndStateFromFieldAndOverridesItsId(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="email" rootId="my-field" disabled="{true}" invalid="{true}">
                <primitives:field.label>Email</primitives:field.label>
                <primitives:input.root>
                    <primitives:input.input />
                </primitives:input.root>
            </primitives:field.root>
        ');

        // Field.Label generates its own id independently of the nested Input.
        $this->assertStringContainsString('id="field:my-field:label"', $html);

        // Input inherits the Field's name/disabled/invalid state...
        $this->assertStringContainsString('name="email"', $html);
        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);

        // ...and its input id is overridden to the Field's generated "control" id (per
        // ComponentPartIdUtility::FIELD_ID_PARTS['input']['control'] = 'input'), rather than
        // generating its own "input:...:input" id - this is what lets a <label for="..."> pointing
        // at the Field's control id reach the actual native input. The wrapping root div still gets
        // its own generated id - only the field-aware control part is overridden.
        $inputTag = $this->extractTag($html, 'input');
        $this->assertStringContainsString('id="field:my-field:control"', $inputTag);
        $this->assertStringNotContainsString('id="input:', $inputTag);
    }

    #[Test]
    public function primitiveLabelNestedInFieldGetsFieldsGeneratedLabelId(): void
    {
        // Recommended pattern per the docs: use the primitive's own `label` part nested inside its
        // `root`, instead of `primitives:field.label`, mirroring how NumberInput/Select do it.
        $html = $this->renderTemplate('
            <primitives:field.root name="email" rootId="my-field">
                <primitives:input.root>
                    <primitives:input.label>Email</primitives:input.label>
                    <primitives:input.input />
                </primitives:input.root>
            </primitives:field.root>
        ');

        $labelTag = $this->extractTag($html, 'label');
        $this->assertStringContainsString('id="field:my-field:label"', $labelTag);
        $this->assertStringNotContainsString('id="input:', $labelTag);

        $inputTag = $this->extractTag($html, 'input');
        $this->assertStringContainsString('id="field:my-field:control"', $inputTag);
    }

    private function extractTag(string $html, string $part): string
    {
        $matched = preg_match('/<[a-z]+[^>]*data-part="' . preg_quote($part, '/') . '"[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected exactly one element with data-part="%s".', $part));

        return $matches[0];
    }
}
