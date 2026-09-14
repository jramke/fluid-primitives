<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Domain\Dto\ResolvedFormPersistedObjects;
use Jramke\FluidPrimitives\Service\ExtbaseFormHiddenFieldsRenderer;
use Jramke\FluidPrimitives\Tests\Helper\TestEntity;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ExtbasePersistedObjectResolver;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;

#[AllowMockObjectsWithoutExpectations]
final class ExtbaseFormHiddenFieldsRendererTest extends TestCase
{
    private function createRenderer(): ExtbaseFormHiddenFieldsRenderer
    {
        return new ExtbaseFormHiddenFieldsRenderer($this->createMock(MvcPropertyMappingConfigurationService::class));
    }

    #[Test]
    public function rendersNoIdentityFieldWhenNoObjectIsBound(): void
    {
        $renderer = $this->createRenderer();
        $objects = new ResolvedFormPersistedObjects(null, []);

        $this->assertSame('', $renderer->renderIdentityFields(
            $objects,
            'eventRegistration',
            'tx_docs_registration',
            false,
        ));
    }

    #[Test]
    public function rendersNoIdentityFieldForANewUnpersistedObject(): void
    {
        $resolver = new ExtbasePersistedObjectResolver();
        $objects = new ResolvedFormPersistedObjects($resolver->resolve(new TestEntity()), []);

        $renderer = $this->createRenderer();

        $this->assertSame('', $renderer->renderIdentityFields(
            $objects,
            'eventRegistration',
            'tx_docs_registration',
            false,
        ));
    }

    #[Test]
    public function rendersIdentityFieldForAPersistedObject(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);
        $objects = new ResolvedFormPersistedObjects($entity, []);

        $renderer = $this->createRenderer();

        $this->assertSame('<input type="hidden" name="tx_docs_registration[eventRegistration][__identity]" value="42" >', $renderer->renderIdentityFields(
            $objects,
            'eventRegistration',
            'tx_docs_registration',
            false,
        ));
    }

    #[Test]
    public function appendsAnXhtmlSelfClosingSlashWhenXhtmlCompliant(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);
        $objects = new ResolvedFormPersistedObjects($entity, []);

        $renderer = $this->createRenderer();

        $this->assertSame('<input type="hidden" name="tx_docs_registration[eventRegistration][__identity]" value="42" />', $renderer->renderIdentityFields(
            $objects,
            'eventRegistration',
            'tx_docs_registration',
            true,
        ));
    }

    #[Test]
    public function rendersIdentityFieldsForNestedPersistedSubObjects(): void
    {
        $nested = new TestEntity();
        $nested->setTestUid(7);

        $entity = new TestEntity();
        $entity->setTestUid(42);
        $objects = new ResolvedFormPersistedObjects($entity, ['nested' => $nested]);

        $renderer = $this->createRenderer();

        $html = $renderer->renderIdentityFields($objects, 'eventRegistration', 'tx_docs_registration', false);

        $this->assertStringContainsString(
            '<input type="hidden" name="tx_docs_registration[eventRegistration][__identity]" value="42" >',
            $html,
        );
        $this->assertStringContainsString(
            '<input type="hidden" name="tx_docs_registration[eventRegistration][nested][__identity]" value="7" >',
            $html,
        );
    }

    #[Test]
    public function includesFieldNamesIdentityAndNestedIdentityInTheTrustedPropertiesToken(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);
        $nested = new TestEntity();
        $nested->setTestUid(7);
        $objects = new ResolvedFormPersistedObjects($entity, ['nested' => $nested]);

        $mvcPropertyMappingConfigurationService = $this->createMock(MvcPropertyMappingConfigurationService::class);
        $mvcPropertyMappingConfigurationService
            ->expects($this->once())
            ->method('generateTrustedPropertiesToken')
            ->with(
                [
                    'tx_docs_registration[eventRegistration][name]',
                    'tx_docs_registration[eventRegistration][__identity]',
                    'tx_docs_registration[eventRegistration][nested][__identity]',
                ],
                'tx_docs_registration',
            )
            ->willReturn('signed-token');

        $renderer = new ExtbaseFormHiddenFieldsRenderer($mvcPropertyMappingConfigurationService);

        $html = $renderer->renderTrustedPropertiesField(
            ['field1' => ['name' => 'name']],
            $objects,
            'eventRegistration',
            'tx_docs_registration',
            false,
        );

        // The field name prefix (the plugin's namespace, needed for Extbase to find this field at
        // all) still applies - only the objectName segment is deliberately left out, since
        // `__trustedProperties` is one token covering the whole form, not any single bound object.
        $this->assertSame(
            '<input type="hidden" name="tx_docs_registration[__trustedProperties]" value="signed-token" >',
            $html,
        );
    }
}
