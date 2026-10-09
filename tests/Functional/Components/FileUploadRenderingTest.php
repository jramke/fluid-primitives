<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class FileUploadRenderingTest extends FunctionalTestCase
{
    private const string TYPE_REJECTED = "{f:constant(name: 'Jramke\\FluidPrimitives\\Enum\\FileUploadItemType::Rejected')}";

    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function nativeInputs(): iterable
    {
        // No automatic `[]` suffixing - the consumer writes `name="impressions[]"` when they want array-style submission.
        yield 'a name with brackets' => ['name="impressions[]" maxFiles="5"', 'impressions[]', true];
        yield 'a name without brackets' => ['name="impressions" maxFiles="5"', 'impressions', true];
        yield 'a single file' => ['name="avatar" maxFiles="1"', 'avatar', false];
    }

    #[Test]
    #[DataProvider('nativeInputs')]
    public function rendersTheNativeInputWithTheNameAsIsAndMultipleOnlyForSeveralFiles(
        string $arguments,
        string $name,
        bool $multiple,
    ): void {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root ' . $arguments . '>
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $input = $this->elementWith($html, 'data-file-upload-hidden-input');
        $this->assertSame('file', $input->get('type'));
        $this->assertSame($name, $input->get('name'));
        $this->assertSame($multiple, $input->has('multiple'));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool, 2: bool}>
     */
    public static function flags(): iterable
    {
        yield 'neither' => ['', false, false];
        yield 'disabled' => ['disabled="{true}"', true, false];
        // a file input has no readonly state: the one the user could not open is disabled
        yield 'read only' => ['readOnly="{true}"', false, true];
    }

    #[Test]
    #[DataProvider('flags')]
    public function carriesTheFlagsOnTheRootAndDisablesTheNativeInputWhenDisabledOrReadOnly(
        string $arguments,
        bool $disabled,
        bool $readOnly,
    ): void {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root ' . $arguments . '>
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $root = $this->elementWith($html, 'data-file-upload-root');
        $this->assertSame($disabled, $root->has('data-disabled'));
        $this->assertSame($readOnly, $root->has('data-readonly'));
        $this->assertSame(
            $disabled || $readOnly,
            $this->elementWith($html, 'data-file-upload-hidden-input')->has('disabled'),
        );
    }

    /**
     * @return iterable<string, array{0: string|array<int|string, mixed>|null, 1: ?string}>
     */
    public static function acceptValues(): iterable
    {
        yield 'a plain string' => ['image/*,.pdf', 'image/*,.pdf'];
        yield 'a map of mime types to extensions' => [
            ['image/png' => ['.png'], 'text/html' => ['.html', '.htm']],
            'image/png,.png,text/html,.html,.htm',
        ];
        yield 'a list, without the tokens that are neither a mime type nor an extension' => [
            ['image/png', 'bogus', '.pdf'],
            'image/png,.pdf',
        ];
        yield 'nothing' => [null, null];
    }

    /**
     * @param string|array<int|string, mixed>|null $accept
     */
    #[Test]
    #[DataProvider('acceptValues')]
    public function rendersTheAcceptAttributeTheWayTheClientComputesIt(
        string|array|null $accept,
        ?string $expected,
    ): void {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root accept="{accept}">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ', ['accept' => $accept]);

        $this->assertSame($expected, $this->elementWith($html, 'data-file-upload-hidden-input')->get('accept'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function dropzoneLabels(): iterable
    {
        yield 'the default' => ['', 'Drag and drop files here or click to browse'];
        yield 'an override' => ['translations="{dropzone: \'Drop it here\'}"', 'Drop it here'];
    }

    #[Test]
    #[DataProvider('dropzoneLabels')]
    public function labelsTheDropzoneFromTheTranslations(string $arguments, string $label): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root ' . $arguments . '>
                <primitives:fileUpload.dropzone>Drop files</primitives:fileUpload.dropzone>
            </primitives:fileUpload.root>
        ');

        $this->assertSame($label, $this->elementWith($html, 'data-file-upload-dropzone')->get('aria-label'));
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, string>}>
     */
    public static function itemTranslations(): iterable
    {
        // `%fileName%` is what the client substitutes with the name of each file: zag's own labels are functions of the file
        yield 'the defaults' => [
            '',
            ['itemPreview' => 'Preview of %fileName%', 'deleteFile' => 'Delete file %fileName%'],
        ];
        yield 'one of them overridden' => [
            'translations="{deleteFile: \'Remove %fileName%\'}"',
            ['itemPreview' => 'Preview of %fileName%', 'deleteFile' => 'Remove %fileName%'],
        ];
    }

    /**
     * @param array<string, string> $expected
     */
    #[Test]
    #[DataProvider('itemTranslations')]
    public function shipsTheItemLabelsToTheClientWithAPlaceholderForTheFileName(
        string $arguments,
        array $expected,
    ): void {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root ' . $arguments . '>
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $rootId = (string)$this->elementWith($html, 'data-file-upload-root')->get('data-file-upload-root');
        $translations = HydrationRegistry::getInstance()->get(
            'primitives',
            'file-upload',
            $rootId,
        )['props']['translations'];

        $this->assertSame($expected, array_intersect_key($translations, $expected));
    }

    #[Test]
    public function shipsNameMaxFilesAcceptAndTheNumberOfExistingFilesToTheClient(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root name="impressions" maxFiles="10" accept="image/*" existingFilesCount="4">
                <primitives:fileUpload.hiddenInput />
            </primitives:fileUpload.root>
        ');

        $rootId = (string)$this->elementWith($html, 'data-file-upload-root')->get('data-file-upload-root');
        $props = HydrationRegistry::getInstance()->get('primitives', 'file-upload', $rootId)['props'];

        $this->assertSame('impressions', $props['name']);
        $this->assertSame(10, $props['maxFiles']);
        $this->assertSame('image/*', $props['accept']);
        // the files that are already persisted count against `maxFiles` on the client
        $this->assertSame(4, $props['existingFilesCount']);
    }

    #[Test]
    public function rendersEachDirectlyAuthoredItemAndItsPartsWithItsOwnValueAndType(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root rootId="upload-root">
                <primitives:fileUpload.itemGroup>
                    <primitives:fileUpload.item type="existing" value="1">
                        <primitives:fileUpload.itemName>First</primitives:fileUpload.itemName>
                    </primitives:fileUpload.item>
                    <primitives:fileUpload.item type="existing" value="2">
                        <primitives:fileUpload.itemName>Second</primitives:fileUpload.itemName>
                    </primitives:fileUpload.item>
                </primitives:fileUpload.itemGroup>
            </primitives:fileUpload.root>
        ');

        // No ids are generated - repeated parts share the root id and differ by `data-value` only.
        $items = $this->elementsWith($html, 'data-file-upload-item');
        $this->assertSame(['1', '2'], array_map(static fn($item): ?string => $item->get('data-value'), $items));
        foreach ($items as $item) {
            $this->assertSame('upload-root', $item->get('data-file-upload-item'));
            // the client tells the files it picked itself from the ones that were already persisted by this
            $this->assertSame('existing', $item->get('data-type'));
            $this->assertNull($item->get('id'));
        }

        $names = $this->elementsWith($html, 'data-file-upload-item-name');
        $this->assertSame(['1', '2'], array_map(static fn($name): ?string => $name->get('data-value'), $names));
        $this->assertSame(
            ['upload-root', 'upload-root'],
            array_map(static fn($name): ?string => $name->get('data-file-upload-item-name'), $names),
        );
        $this->assertNull($names[0]->get('id'));
    }

    #[Test]
    public function rendersTheItemGroupWithItsTypeAsTheValueTheClientLooksItUpBy(): void
    {
        $html = $this->renderTemplate(
            '
            <primitives:fileUpload.root>
                <primitives:fileUpload.itemGroup type="' .
            self::TYPE_REJECTED .
            '">
                    <primitives:fileUpload.emptyState type="' .
            self::TYPE_REJECTED .
            '">No rejected files.</primitives:fileUpload.emptyState>
                </primitives:fileUpload.itemGroup>
            </primitives:fileUpload.root>
        ',
        );

        $group = $this->elementWith($html, 'data-file-upload-item-group');
        $this->assertSame('rejected', $group->get('data-value'));
        $this->assertSame('rejected', $group->get('data-type'));
        $this->assertSame('rejected', $this->elementWith($html, 'data-file-upload-empty-state')->get('data-value'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function itemTemplates(): iterable
    {
        // accepted is also the one a rejected file falls back to when there is no template of its own
        yield 'accepted by default' => [
            '',
            'data-file-upload-item-template',
            'data-file-upload-rejected-item-template',
        ];
        yield 'rejected' => [
            'type="' . self::TYPE_REJECTED . '"',
            'data-file-upload-rejected-item-template',
            'data-file-upload-item-template',
        ];
    }

    #[Test]
    #[DataProvider('itemTemplates')]
    public function rendersTheItemTemplateAsATemplateElementWithItsPartsMarkedButWithoutAFile(
        string $arguments,
        string $marker,
        string $otherMarker,
    ): void {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root rootId="upload-root">
                <primitives:fileUpload.itemTemplate ' . $arguments . '>
                    <primitives:fileUpload.item>
                        <primitives:fileUpload.itemPreview match="image/*">
                            <primitives:fileUpload.itemPreviewFallback />
                        </primitives:fileUpload.itemPreview>
                        <primitives:fileUpload.itemName />
                        <primitives:fileUpload.itemSizeText />
                        <primitives:fileUpload.itemError>Something went wrong</primitives:fileUpload.itemError>
                        <primitives:fileUpload.itemDeleteTrigger>Delete</primitives:fileUpload.itemDeleteTrigger>
                    </primitives:fileUpload.item>
                </primitives:fileUpload.itemTemplate>
            </primitives:fileUpload.root>
        ');

        $template = $this->elementWith($html, $marker);
        $this->assertSame('template', $template->tag);
        $this->assertSame('upload-root', $template->get($marker));
        $this->assertSame([], $this->elementsWith($html, $otherMarker));

        // the client stamps the parts with the value of each file it picks, so none has one here
        $parts = [
            'data-file-upload-item',
            'data-file-upload-item-preview',
            'data-file-upload-item-preview-fallback',
            'data-file-upload-item-name',
            'data-file-upload-item-size-text',
            'data-file-upload-item-error',
            'data-file-upload-item-delete-trigger',
        ];
        foreach ($parts as $part) {
            $element = $this->elementWith($html, $part);
            $this->assertSame('upload-root', $element->get($part), $part);
            $this->assertFalse($element->has('data-value'), $part);
        }

        // the client picks the preview of the first one whose pattern matches the file
        $this->assertSame('image/*', $this->elementWith($html, 'data-file-upload-item-preview')->get('data-match'));
        $this->assertTrue($this->elementWith($html, 'data-file-upload-item-error')->has('hidden'));
    }

    #[Test]
    public function rendersTheClearTriggerHiddenUntilThereAreFilesToClear(): void
    {
        $html = $this->renderTemplate('
            <primitives:fileUpload.root>
                <primitives:fileUpload.clearTrigger>Clear</primitives:fileUpload.clearTrigger>
            </primitives:fileUpload.root>
        ');

        $clear = $this->elementWith($html, 'data-file-upload-clear-trigger');
        $this->assertSame('button', $clear->tag);
        $this->assertTrue($clear->has('hidden'));
    }
}
