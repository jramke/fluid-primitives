<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TextareaRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HydrationRegistry::getInstance()->clear();
    }

    #[Test]
    public function rendersRootAndNativeTextareaWithRefsAndRows(): void
    {
        $html = $this->renderTemplate('
            <primitives:textarea.root defaultValue="hello world" rows="6">
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $this->assertStringContainsString('data-scope="textarea"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
        $this->assertStringContainsString('data-part="textarea"', $html);
        $this->assertStringContainsString('rows="6"', $html);
        $this->assertStringContainsString('>hello world</textarea>', $html);
    }

    #[Test]
    public function rendersPlaceholderAndAutocompleteServerSideWhenSet(): void
    {
        $html = $this->renderTemplate('
            <primitives:textarea.root placeholder="Type your message here..." autocomplete="street-address">
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $this->assertStringContainsString('placeholder="Type your message here..."', $html);
        $this->assertStringContainsString('autocomplete="street-address"', $html);
    }

    #[Test]
    public function rendersWordCountTextServerSideWhenMaxLengthIsSet(): void
    {
        $html = $this->renderTemplate('
            <primitives:textarea.root defaultValue="hello" maxLength="10">
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ');

        $this->assertStringContainsString('maxlength="10"', $html);
        $this->assertStringContainsString('>5 / 10 characters<', $html);
    }

    #[Test]
    public function omitsWordCountTextWithoutMaxLength(): void
    {
        $html = $this->renderTemplate('
            <primitives:textarea.root defaultValue="hello">
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ');

        $this->assertStringNotContainsString('characters', $html);
    }

    #[Test]
    public function omitsWordCountTextWhenTranslationDisabled(): void
    {
        $html = $this->renderTemplate('
            <primitives:textarea.root defaultValue="hello" maxLength="10" translations="{wordCount: false}">
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ');

        $this->assertStringNotContainsString('characters', $html);
    }

    #[Test]
    public function rendersGermanWordCountWhenLocaleIsGerman(): void
    {
        $this->setRequestLocale('de_DE');

        $html = $this->renderTemplate('
            <primitives:textarea.root defaultValue="hallo" maxLength="10">
                <primitives:textarea.textarea />
                <primitives:textarea.wordCount />
            </primitives:textarea.root>
        ');

        $this->assertStringContainsString('>5 / 10 Zeichen<', $html);
    }

    #[Test]
    public function includesMergedTranslationsInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:textarea.root maxLength="10" translations="{wordCount: \'%count% of %max%\'}">
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $textareaData = array_values($hydrationData['textarea'])[0];

        $this->assertSame(['wordCount' => '%count% of %max%'], $textareaData['props']['translations']);
    }

    #[Test]
    public function includesSubmitOnInHydrationDataWhenSet(): void
    {
        $this->renderTemplate('
            <primitives:textarea.root submitOn="{f:constant(name: \'Jramke\FluidPrimitives\Enum\TextareaSubmitOn::ModEnter\')}">
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $textareaData = array_values($hydrationData['textarea'])[0];

        // EnumUtility::normalize() unwraps the backed enum to its plain string value before it
        // ever reaches the hydration payload / client-side TS (which still just sees 'mod+enter').
        $this->assertSame('mod+enter', $textareaData['props']['submitOn']);
    }

    #[Test]
    public function includesAnnounceDebounceDefaultInHydrationData(): void
    {
        $this->renderTemplate('
            <primitives:textarea.root>
                <primitives:textarea.textarea />
            </primitives:textarea.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll()['primitives'] ?? [];
        $textareaData = array_values($hydrationData['textarea'])[0];

        $this->assertSame(600, $textareaData['props']['announceDebounce']);
    }

    #[Test]
    public function propagatesNameAndStateFromFieldAndOverridesItsId(): void
    {
        $html = $this->renderTemplate('
            <primitives:field.root name="comment" rootId="my-field" disabled="{true}" invalid="{true}">
                <primitives:field.label>Comment</primitives:field.label>
                <primitives:textarea.root>
                    <primitives:textarea.textarea />
                </primitives:textarea.root>
            </primitives:field.root>
        ');

        // Field.Label generates its own id independently of the nested Textarea.
        $this->assertStringContainsString('id="field:my-field:label"', $html);

        // Textarea inherits the Field's name/disabled/invalid state...
        $this->assertStringContainsString('name="comment"', $html);
        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('aria-invalid="true"', $html);

        // ...and its textarea id is overridden to the Field's generated "control" id (per
        // ComponentPartIdUtility::FIELD_ID_PARTS['textarea']['control'] = 'textarea'), rather than
        // generating its own "textarea:...:textarea" id - this is what lets a <label for="..."> pointing
        // at the Field's control id reach the actual native textarea. The wrapping root div still gets
        // its own generated id - only the field-aware control part is overridden.
        $textareaTag = $this->extractTag($html, 'textarea');
        $this->assertStringContainsString('id="field:my-field:control"', $textareaTag);
        $this->assertStringNotContainsString('id="textarea:', $textareaTag);
    }

    #[Test]
    public function primitiveLabelNestedInFieldGetsFieldsGeneratedLabelId(): void
    {
        // Recommended pattern per the docs: use the primitive's own `label` part nested inside its
        // `root`, instead of `primitives:field.label`, mirroring how NumberInput/Select do it.
        $html = $this->renderTemplate('
            <primitives:field.root name="comment" rootId="my-field">
                <primitives:textarea.root>
                    <primitives:textarea.label>Comment</primitives:textarea.label>
                    <primitives:textarea.textarea />
                </primitives:textarea.root>
            </primitives:field.root>
        ');

        $labelTag = $this->extractTag($html, 'label');
        $this->assertStringContainsString('id="field:my-field:label"', $labelTag);
        $this->assertStringNotContainsString('id="textarea:', $labelTag);

        $textareaTag = $this->extractTag($html, 'textarea');
        $this->assertStringContainsString('id="field:my-field:control"', $textareaTag);
    }

    private function extractTag(string $html, string $part): string
    {
        $matched = preg_match('/<[a-z]+[^>]*data-part="' . preg_quote($part, '/') . '"[^>]*>/', $html, $matches);
        $this->assertSame(1, $matched, sprintf('Expected exactly one element with data-part="%s".', $part));

        return $matches[0];
    }
}
