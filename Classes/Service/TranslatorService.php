<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Service;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
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
        $translated = $this->getTranslator($request)->translate($key, self::TRANSLATIONS_FILE, $arguments);
        return $translated === null ? null : (string)$translated;
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

        if (($this->translators[$cacheKey] ?? null) !== null) {
            return $this->translators[$cacheKey];
        }

        $this->translators[$cacheKey] = $siteLanguage instanceof SiteLanguage
            ? $this->languageServiceFactory->createFromSiteLanguage($siteLanguage)
            // No site/language attribute on the request (e.g. outside a normal frontend request) -
            // fall back to the same default TYPO3 itself uses when no user preference is known.
            : $this->languageServiceFactory->createFromUserPreferences(null);

        return $this->translators[$cacheKey];
    }

    private function getSiteLanguage(ServerRequestInterface $request): ?SiteLanguage
    {
        return $request->getAttribute('language') ?? $request->getAttribute('site')?->getDefaultLanguage();
    }
}
