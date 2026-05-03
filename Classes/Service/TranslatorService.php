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
    private array $translators = [];

    public function __construct(
        private readonly LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function translate(string $key, ServerRequestInterface $request): ?string
    {
        return $this->getTranslator($request)->translate(
            $key,
            'EXT:fluid_primitives/Resources/Private/Language/locallang.xlf',
        );
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
        return $request->getAttribute('language') ?? $request->getAttribute('site')->getDefaultLanguage() ?? null;
    }
}
