<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Traits;

use Jramke\FluidPrimitives\Service\TranslatorService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Shared by Context classes that expose a `translations` prop overridable per-instance, merged over
 * localized defaults resolved through {@see TranslatorService}.
 *
 * The abstract methods below are this trait's explicit contract with whatever class uses it - `get()`
 * and `getRequest()` are already satisfied by extending `AbstractComponentContext`; `getTranslator()`
 * additionally needs a one-line implementation returning the using class's own constructor-injected
 * `TranslatorService`, since a trait cannot receive its own dependency-injected instance.
 */
trait HasTranslationsTrait
{
    abstract protected function getTranslator(): TranslatorService;

    abstract public function get(string $key): mixed;

    abstract public function getRequest(): ServerRequestInterface;

    /**
     * @param array<string, string> $translationKeysByPropertyName Maps the returned array's keys to
     *   their locallang translation key, e.g. ['triggerLabel' => 'combobox.triggerLabel'].
     */
    protected function translationsWithDefaults(array $translationKeysByPropertyName): array
    {
        $rawOverrides = $this->get('translations');
        $overrides = is_array($rawOverrides) ? $rawOverrides : [];

        $defaults = [];
        foreach ($translationKeysByPropertyName as $propertyName => $translationKey) {
            $defaults[$propertyName] = $this->getTranslator()->translate($translationKey, $this->getRequest());
        }

        return array_merge($defaults, $overrides);
    }
}
