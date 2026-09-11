<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class FileUploadRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        HydrationRegistry::getInstance()->clear();
    }

    #[Test]
    public function rendersRootWithDataAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root disabled="{true}" readOnly="{true}">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-scope="file-upload"', $html);
        $this->assertStringContainsString('data-part="root"', $html);
        $this->assertStringContainsString('data-disabled', $html);
        $this->assertStringContainsString('data-readonly', $html);
    }

    #[Test]
    public function rendersNameAsIsWithoutAutomaticBracketSuffixing(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root name="impressions[]" maxFiles="5">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('name="impressions[]"', $html);
    }

    #[Test]
    public function doesNotAppendBracketsWhenNameIsGivenWithoutThem(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root name="impressions" maxFiles="5">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('name="impressions"', $html);
        $this->assertStringNotContainsString('name="impressions[]"', $html);
    }

    #[Test]
    public function rendersMultipleAttributeWhenMoreThanOneFileIsAllowed(): void
    {
        $singleHtml = $this->renderTemplate('
            <primitives:fileUpload.root name="avatar" maxFiles="1">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');
        $this->assertStringNotContainsString('multiple', $singleHtml);

        $multiHtml = $this->renderTemplate('
            <primitives:fileUpload.root name="impressions" maxFiles="5">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');
        $this->assertStringContainsString('multiple', $multiHtml);
    }

    #[Test]
    public function rendersAcceptAttributeFromAPlainString(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root accept="image/*,.pdf">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('accept="image/*,.pdf"', $html);
    }

    #[Test]
    public function rendersAcceptAttributeFromAMimeTypeExtensionMap(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root accept="{acceptMap}">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ', [
            'acceptMap' => [
                'image/png' => ['.png'],
                'text/html' => ['.html', '.htm'],
            ],
        ]);

        $this->assertStringContainsString('accept="image/png,.png,text/html,.html,.htm"', $html);
    }

    #[Test]
    public function omitsAcceptAttributeWhenNotProvided(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $this->assertStringNotContainsString('accept=', $html);
    }

    #[Test]
    public function rendersEnglishDropzoneLabelByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.dropzone>Drop files</primitives:fileUpload.dropzone>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('aria-label="Drag and drop files here or click to browse"', $html);
    }

    #[Test]
    public function prefersTranslationOverrideOverLocalizedDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root translations="{dropzone: \'Drop it here\'}">
                <primitives:fileUpload.dropzone>Drop files</primitives:fileUpload.dropzone>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('aria-label="Drop it here"', $html);
    }

    #[Test]
    public function exposesItemPreviewAndDeleteFileTranslationsAsFileNamePlaceholderStrings(): void
    {
        $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $translations = array_values($hydrationData['file-upload'])[0]['props']['translations'];

        $this->assertSame('Preview of %fileName%', $translations['itemPreview']);
        $this->assertSame('Delete file %fileName%', $translations['deleteFile']);
    }

    #[Test]
    public function allowsOverridingItemPreviewAndDeleteFileTranslations(): void
    {
        $this->renderTemplate('
            <primitives:fileUpload.root translations="{deleteFile: \'Remove %fileName%\'}">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $translations = array_values($hydrationData['file-upload'])[0]['props']['translations'];

        $this->assertSame('Remove %fileName%', $translations['deleteFile']);
        $this->assertSame('Preview of %fileName%', $translations['itemPreview']);
    }

    #[Test]
    public function passesThroughAFalseTranslationOverrideToOmitIt(): void
    {
        $this->renderTemplate('
            <primitives:fileUpload.root translations="{deleteFile: false}">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $translations = array_values($hydrationData['file-upload'])[0]['props']['translations'];

        $this->assertFalse($translations['deleteFile']);
    }

    #[Test]
    public function rendersItemWithGivenTypeAsDataAttributeForDirectlyAuthoredItems(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.itemGroup>
                    <primitives:fileUpload.item type="existing">Existing file</primitives:fileUpload.item>
                </primitives:fileUpload.itemGroup>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-type="existing"', $html);
    }

    #[Test]
    public function rendersItemErrorHiddenByDefaultWithRefAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <ui:template name="itemTemplate" context="file-upload">
                    <primitives:fileUpload.item>
                        <primitives:fileUpload.itemError>Something went wrong</primitives:fileUpload.itemError>
                    </primitives:fileUpload.item>
                </ui:template>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-part="item-error"', $html);
        $this->assertMatchesRegularExpression(
            '/data-part="item-error"[^>]*hidden|hidden[^>]*data-part="item-error"/s',
            $html,
        );
    }

    #[Test]
    public function rendersItemPreviewFallbackWithRefAttributes(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <ui:template name="itemTemplate" context="file-upload">
                    <primitives:fileUpload.item>
                        <primitives:fileUpload.itemPreview match=".*">
                            <primitives:fileUpload.itemPreviewFallback />
                        </primitives:fileUpload.itemPreview>
                    </primitives:fileUpload.item>
                </ui:template>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-part="item-preview-fallback"', $html);
    }

    #[Test]
    public function rendersItemTemplateDefaultingToAcceptedName(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.itemTemplate>
                    <primitives:fileUpload.item>Test</primitives:fileUpload.item>
                </primitives:fileUpload.itemTemplate>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-part="item-template"', $html);
        $this->assertStringContainsString('data-part="item"', $html);
    }

    #[Test]
    public function rendersItemTemplateWithRejectedTypeAsRejectedItemTemplateName(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.itemTemplate type="{f:constant(name: \'Jramke\FluidPrimitives\Enum\FileUploadItemType::Rejected\')}">
                    <primitives:fileUpload.item>Test</primitives:fileUpload.item>
                </primitives:fileUpload.itemTemplate>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-part="rejected-item-template"', $html);
    }

    /**
     * Documents a real limitation of ambient `ui:ref` resolution (no explicit `context` argument):
     * it only reliably resolves when nothing else that goes through `ComponentRenderer` (another
     * primitive part, e.g. `itemPreview`/`itemName`) has rendered earlier as a sibling within the
     * same slot content - some part of that render leaves the *ambient* `component`/`context`
     * Fluid variables (read directly off the current rendering context) unset for whatever renders
     * next, even though the enclosing component's context is still correctly on `ContextService`'s
     * stack (which is what `ui:ref`'s own explicit `context` argument reads instead - see the next
     * test). Consumer-authored elements that share an item template with any other primitive part
     * (the common case) therefore need `context: 'file-upload'` explicitly - ambient resolution is
     * only dependable for content with no such sibling, or for a primitive's own `.html` body.
     */
    #[Test]
    public function ambientRefFailsAfterAnotherComponentSiblingRendersFirstInsideItemTemplate(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The ref ViewHelper can only be used inside a component context.');

        $this->renderTemplate('
            <primitives:fileUpload.root rootId="diag-root">
                <primitives:fileUpload.itemTemplate>
                    <primitives:fileUpload.item>
                        <primitives:fileUpload.itemName />
                        <div {ui:ref(name: \'diagField\', withId: false)}>diag</div>
                    </primitives:fileUpload.item>
                </primitives:fileUpload.itemTemplate>
            </primitives:fileUpload.root>
        ');
    }

    #[Test]
    public function explicitContextRefWorksAfterAnotherComponentSiblingRendersFirstInsideItemTemplate(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root rootId="diag-root-2">
                <primitives:fileUpload.itemTemplate>
                    <primitives:fileUpload.item>
                        <primitives:fileUpload.itemPreview match=".*">
                            <primitives:fileUpload.itemPreviewFallback />
                        </primitives:fileUpload.itemPreview>
                        <div {ui:ref(name: \'diagField\', withId: false, context: \'file-upload\')}>diag</div>
                    </primitives:fileUpload.item>
                </primitives:fileUpload.itemTemplate>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-part="diag-field"', $html);
    }

    #[Test]
    public function rendersItemGroupWithTypeAsDataAttributeAndValue(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.itemGroup type="{f:constant(name: \'Jramke\FluidPrimitives\Enum\FileUploadItemType::Rejected\')}">
                    <primitives:fileUpload.emptyState>No rejected files.</primitives:fileUpload.emptyState>
                </primitives:fileUpload.itemGroup>
            </primitives:fileUpload.root>
        ');

        $this->assertStringContainsString('data-type="rejected"', $html);
        $this->assertStringContainsString('data-value="rejected"', $html);
    }

    #[Test]
    public function rendersClearTriggerHiddenByDefault(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.clearTrigger>Clear</primitives:fileUpload.clearTrigger>
            </primitives:fileUpload.root>
        ');

        $this->assertMatchesRegularExpression(
            '/data-part="clear-trigger"[^>]*hidden|hidden[^>]*data-part="clear-trigger"/s',
            $html,
        );
    }

    #[Test]
    public function exposesExistingFilesCountToTheClientForMaxFilesAccounting(): void
    {
        $this->renderTemplate('
            <primitives:fileUpload.root maxFiles="10" existingFilesCount="4">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $props = array_values($hydrationData['file-upload'])[0]['props'];

        $this->assertSame(10, $props['maxFiles']);
        $this->assertSame(4, $props['existingFilesCount']);
    }

    #[Test]
    public function registersComponentWithPropsInHydrationRegistry(): void
    {
        $this->renderTemplate('
            <primitives:fileUpload.root name="impressions" maxFiles="5" accept="image/*">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $hydrationData = HydrationRegistry::getInstance()->getAll();

        $this->assertArrayHasKey('file-upload', $hydrationData);
        $fileUploadData = array_values($hydrationData['file-upload'])[0];
        $this->assertSame('impressions', $fileUploadData['props']['name']);
        $this->assertSame(5, $fileUploadData['props']['maxFiles']);
        $this->assertSame('image/*', $fileUploadData['props']['accept']);
    }
}
