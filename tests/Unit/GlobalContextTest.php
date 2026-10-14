<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Contexts\GlobalContext;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class GlobalContextTest extends TestCase
{
    #[Test]
    public function takesTheLocaleFromTheLanguageOfTheRequestAsABcp47Tag(): void
    {
        GeneralUtility::addInstance(
            TranslatorService::class,
            new TranslatorService($this->createStub(LanguageServiceFactory::class)),
        );
        $language = new SiteLanguage(0, 'de_DE', new Uri('https://example.com/'), []);
        $context = new GlobalContext();
        $context->initialize((new ServerRequest())->withAttribute('language', $language));

        $this->assertSame('de-DE', $context->get('locale'));
    }

    #[Test]
    public function hasNoLocaleWithoutARequest(): void
    {
        $this->assertNull((new GlobalContext())->get('locale'));
    }

    #[Test]
    public function isNotInDebugWithoutAnInitializedEnvironment(): void
    {
        $this->assertFalse((new GlobalContext())->get('debug'));
    }

    #[Test]
    public function doesNotAllowASubclassToChangeLocaleAndDebug(): void
    {
        foreach (['getLocale', 'getDebug'] as $method) {
            $this->assertTrue((new \ReflectionMethod(GlobalContext::class, $method))->isFinal(), $method);
        }
    }
}
