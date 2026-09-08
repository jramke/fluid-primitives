<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Component\ComponentCollectionInterface;
use Jramke\FluidPrimitives\Tests\Helper\TestEntity;
use Jramke\FluidPrimitives\Tests\Helper\TestFormContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperVariableContainer;

#[AllowMockObjectsWithoutExpectations]
final class FormContextTest extends TestCase
{
    /** @param array<string, array{name?: string}> $fieldContextData */
    private function createContext(array $fieldContextData = []): TestFormContext
    {
        // `shouldUseXHtmlSlash()` derives the doctype from the request's `frontend.typoscript`
        // attribute via `DocType::createFromRequest()`; a request with no such attribute resolves
        // to the html5 default (no XHTML self-closing slash), which is what's asserted below.
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getAttribute')->willReturn(null);

        $renderingContext = $this->createMock(RenderingContextInterface::class);
        $renderingContext->method('getAttribute')->willReturn($request);

        $variableContainer = $this->createMock(ViewHelperVariableContainer::class);
        $variableContainer->method('getAll')->willReturn($fieldContextData);
        $renderingContext->method('getViewHelperVariableContainer')->willReturn($variableContainer);

        $context = new TestFormContext(
            $this->createMock(MvcPropertyMappingConfigurationService::class),
            $this->createMock(ExtensionService::class),
            $this->createMock(UriBuilder::class),
        );
        $context->initialize(
            $renderingContext,
            $renderingContext,
            $this->createMock(ComponentCollectionInterface::class),
        );
        $context->setTestFieldNamePrefix('tx_docs_registration');

        return $context;
    }

    #[Test]
    public function prefixesNestedDotNotationFieldNamesForTrustedPropertiesAndSubmission(): void
    {
        $context = $this->createContext();

        $this->assertSame('tx_docs_registration[eventRegistration][person][name]', $context->exposePrefixFieldName(
            'person.name',
            'eventRegistration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $context->exposePrefixFieldName(
            'persons[0].name',
            'eventRegistration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $context->exposePrefixFieldName(
            'persons.0.name',
            'eventRegistration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][tags][]', $context->exposePrefixFieldName(
            'tags[]',
            'eventRegistration',
        ));
    }

    #[Test]
    public function rendersNoIdentityFieldWhenNoObjectIsBound(): void
    {
        $context = $this->createContext();

        $this->assertSame('', $context->exposeRenderHiddenIdentityField());
    }

    #[Test]
    public function rendersNoIdentityFieldForANewObject(): void
    {
        $context = $this->createContext();
        $context->set('object', new TestEntity());
        $context->set('objectName', 'eventRegistration');

        $this->assertSame('', $context->exposeRenderHiddenIdentityField());
    }

    #[Test]
    public function rendersIdentityFieldForAPersistedObject(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);

        $context = $this->createContext();
        $context->set('object', $entity);
        $context->set('objectName', 'eventRegistration');

        $this->assertSame(
            '<input type="hidden" name="tx_docs_registration[eventRegistration][__identity]" value="42" >',
            $context->exposeRenderHiddenIdentityField(),
        );
    }

    #[Test]
    public function rendersIdentityFieldForAPersistedNestedSubObject(): void
    {
        $nested = new TestEntity();
        $nested->setTestUid(7);

        $entity = new TestEntity();
        $entity->setTestUid(42);
        $entity->setNested($nested);

        $context = $this->createContext([
            'field1' => ['name' => 'nested.name'],
            'field2' => ['name' => 'nested.email'],
        ]);
        $context->set('object', $entity);
        $context->set('objectName', 'eventRegistration');

        $html = "<form>\n</form>";
        $context->afterRendering($html);

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
    public function rendersNoIdentityFieldForANewNestedSubObject(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);
        $entity->setNested(new TestEntity());

        $context = $this->createContext([
            'field1' => ['name' => 'nested.name'],
        ]);
        $context->set('object', $entity);
        $context->set('objectName', 'eventRegistration');

        $html = "<form>\n</form>";
        $context->afterRendering($html);

        $this->assertStringNotContainsString('[nested][__identity]', $html);
    }
}
