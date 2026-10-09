<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

final class TranslatorServiceTest extends TestCase
{
    #[Test]
    public function hasNoLocaleWithoutASiteLanguageSoComponentsLeaveTheirOwnDefaultInPlace(): void
    {
        $translator = new TranslatorService($this->createStub(LanguageServiceFactory::class));
        $request = new ServerRequest();

        // a string here, even an empty one, would be shipped to the client as a locale
        $this->assertNull($translator->getLocale($request));
        $this->assertSame(
            'de-DE',
            $translator->getLocale($request->withAttribute(
                'language',
                new SiteLanguage(0, 'de_DE', new Uri('https://example.com/'), []),
            )),
        );
    }
}
