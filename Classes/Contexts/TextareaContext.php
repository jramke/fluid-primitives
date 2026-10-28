<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasCharacterCountTrait;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class TextareaContext extends AbstractComponentContext
{
    use HasCharacterCountTrait;
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    /**
     * @return array{wordCount: string, wordCountOver: string}
     */
    #[ExposeToClient]
    public function getTranslations(): array
    {
        // translationsWithDefaults() merges user overrides, so it can't be typed more precisely than array.
        // @mago-expect analysis:invalid-return-statement
        return $this->translationsWithDefaults([
            'wordCount' => 'textarea.wordCount',
            'wordCountOver' => 'textarea.wordCountOver',
        ]);
    }
}
