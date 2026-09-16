<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class TextareaContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    #[ExposeToClient]
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'wordCount' => 'textarea.wordCount',
        ]);
    }

    /**
     * Server-rendered so the word count part isn't empty before hydration. `null` when there's no
     * `maxLength` to count against, or the translation was explicitly disabled via `translations.wordCount: false`.
     */
    public function getWordCountText(): ?string
    {
        $maxLength = Typed::intOrNull($this->get('maxLength'));
        if ($maxLength === null) {
            return null;
        }

        $template = $this->getTranslations()['wordCount'] ?? null;
        if (!is_string($template) || $template === '') {
            return null;
        }

        $count = mb_strlen(Typed::string($this->get('defaultValue')));

        return str_replace(['%count%', '%max%'], [(string)$count, (string)$maxLength], $template);
    }
}
