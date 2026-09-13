<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

/**
 * Shared by Context classes that expose a `translations` prop overridable per-instance, merged over
 * localized defaults resolved through {@see \Jramke\FluidPrimitives\Service\TranslatorService}.
 * Requires the using class to have a `private readonly TranslatorService $translator` property.
 */
trait HasTranslationsTrait
{
    /**
     * @param array<string, string> $translationKeysByPropertyName Maps the returned array's keys to
     *   their locallang translation key, e.g. ['triggerLabel' => 'combobox.triggerLabel'].
     */
    protected function translationsWithDefaults(array $translationKeysByPropertyName): array
    {
        $overrides = $this->get('translations') ?? [];

        $defaults = [];
        foreach ($translationKeysByPropertyName as $propertyName => $translationKey) {
            $defaults[$propertyName] = $this->translator->translate($translationKey, $this->getRequest());
        }

        return array_merge($defaults, $overrides);
    }
}
