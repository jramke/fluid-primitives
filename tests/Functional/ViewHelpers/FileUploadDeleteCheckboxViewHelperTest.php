<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use RuntimeException;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

final class FileUploadDeleteCheckboxViewHelperTest extends FunctionalTestCase
{
    private function createFileReference(int $uid): FileReference
    {
        $fileReference = new FileReference();
        // `uid` has no public setter by design (Extbase persistence owns it) - direct reflection is
        // the only way to give a plain, unpersisted test object a uid.
        (new ReflectionProperty($fileReference, 'uid'))->setValue($fileReference, $uid);

        return $fileReference;
    }

    /**
     * Extracts the checkbox's `value` attribute, HTML-entity-decodes it (the rendered attribute has
     * its embedded JSON quotes escaped as `&quot;`, which would otherwise break both the HMAC
     * comparison and json_decode), verifies its HMAC, and returns the decoded payload.
     */
    private function extractAndValidateToken(string $html): array
    {
        preg_match('/value="([^"]+)"/', $html, $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Expected the rendered checkbox to carry a value.');

        $hashService = $this->get(HashService::class);
        $payload = $hashService->validateAndStripHmac(html_entity_decode($matches[1]), '@delete');

        return json_decode((string)$payload, true);
    }

    #[Test]
    public function rendersACheckboxWithAValidHmacSignedToken(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <primitives:field.root name="logo">
                    <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" />
                </primitives:field.root>
            </primitives:form.root>
        ', ['fileReference' => $this->createFileReference(42)]);

        $this->assertMatchesRegularExpression('/<input[^>]*type="checkbox"/', $html);

        $decoded = $this->extractAndValidateToken($html);

        $this->assertSame(42, $decoded['fileReference']);
        $this->assertSame('logo', $decoded['property']);
    }

    #[Test]
    public function inheritsThePropertyFromTheEnclosingFieldWhenNotGivenExplicitly(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <primitives:field.root name="logo">
                    <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" />
                </primitives:field.root>
            </primitives:form.root>
        ', ['fileReference' => $this->createFileReference(42)]);

        $this->assertSame('logo', $this->extractAndValidateToken($html)['property']);
    }

    #[Test]
    public function explicitPropertyOverridesTheEnclosingFieldsName(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <primitives:field.root name="logo">
                    <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" property="banner" />
                </primitives:field.root>
            </primitives:form.root>
        ', ['fileReference' => $this->createFileReference(42)]);

        $this->assertSame('banner', $this->extractAndValidateToken($html)['property']);
    }

    #[Test]
    public function buildsAnArrayStyleNameFromTheFormsObjectNameAndADeleteMarker(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <primitives:field.root name="logo">
                    <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" />
                </primitives:field.root>
            </primitives:form.root>
        ', ['fileReference' => $this->createFileReference(42)]);

        $this->assertStringContainsString('name="@delete[conference][]"', $html);
    }

    #[Test]
    public function throwsWhenUsedOutsideAFormRoot(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('can only be used inside a <ui:form.root>');

        $this->renderTemplate('
            <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" property="logo" />
        ', ['fileReference' => $this->createFileReference(42)]);
    }

    #[Test]
    public function throwsWhenTheFormHasNoObjectName(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires the enclosing <ui:form.root> to have an "objectName"');

        $this->renderTemplate('
            <primitives:form.root actionUri="/submit">
                <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" property="logo" />
            </primitives:form.root>
        ', ['fileReference' => $this->createFileReference(42)]);
    }

    #[Test]
    public function throwsWhenNeitherPropertyNorAnEnclosingFieldIsGiven(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a "property" argument');

        $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <ui:fileUploadDeleteCheckbox fileReference="{fileReference}" />
            </primitives:form.root>
        ', ['fileReference' => $this->createFileReference(42)]);
    }
}
