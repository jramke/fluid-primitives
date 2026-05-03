<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

#[Autoconfigure(public: true)]
final class TranslatorService
{
    private const TRANSLATIONS_FILE = 'EXT:fluid_primitives/Resources/Private/Language/locallang.xlf';

    private array $translators = [];

    public function __construct(
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function translate(string $key, ServerRequestInterface $request, array $arguments = []): ?string
    {
        if ((new Typo3Version())->getMajorVersion() >= 14) {
            return $this->getTranslator($request)->translate($key, self::TRANSLATIONS_FILE, $arguments);
        }

        $llString = $this->getTranslator($request)->sL('LLL:' . self::TRANSLATIONS_FILE . ':' . $key);
        if (!$llString) {
            return null;
        }

        return sprintf($llString, ...$arguments);
    }

    public function getLocale(ServerRequestInterface $request): ?string
    {
        $siteLanguage = $this->getSiteLanguage($request);
        return (string)$siteLanguage?->getLocale();
    }

    private function getTranslator(ServerRequestInterface $request): LanguageService
    {
        $siteLanguage = $this->getSiteLanguage($request);
        $cacheKey = $siteLanguage?->getLanguageId() ?? 'default';

        if (isset($this->translators[$cacheKey])) {
            return $this->translators[$cacheKey];
        }

        $this->translators[$cacheKey] = $this->languageServiceFactory->createFromSiteLanguage($siteLanguage);

        return $this->translators[$cacheKey];
    }

    private function getSiteLanguage(ServerRequestInterface $request): ?SiteLanguage
    {
        return $request->getAttribute('language') ?? $request->getAttribute('site')?->getDefaultLanguage();
    }
}
