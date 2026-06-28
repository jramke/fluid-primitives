<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Helper\TestFormContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Extbase\Mvc\Controller\MvcPropertyMappingConfigurationService;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Service\ExtensionService;

#[AllowMockObjectsWithoutExpectations]
final class FormContextTest extends TestCase
{
    #[Test]
    public function prefixesNestedDotNotationFieldNamesForTrustedPropertiesAndSubmission(): void
    {
        $context = new TestFormContext(
            $this->createMock(MvcPropertyMappingConfigurationService::class),
            $this->createMock(ExtensionService::class),
            $this->createMock(PageRenderer::class),
            $this->createMock(UriBuilder::class),
        );
        $context->setTestFieldNamePrefix('tx_docs_registration');

        $this->assertSame('tx_docs_registration[eventRegistration][person][name]', $context->exposePrefixFieldName(
            'person.name',
            'eventRegistration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $context->exposePrefixFieldName(
            'persons[0].name',
            'eventRegistration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][tags][]', $context->exposePrefixFieldName(
            'tags[]',
            'eventRegistration',
        ));
    }
}
