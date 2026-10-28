<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasCharacterCountTrait;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
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
     * What goes between the tags of the textarea. The HTML parser drops a line break right behind the
     * opening tag, so the default value follows one that is the parser's, and a value that starts
     * with a line break keeps its own. It is built here and not written into the template, where a
     * formatter that breaks the line would add whitespace to every value.
     */
    public function getDefaultValueContent(): string
    {
        return "\n" . Typed::string($this->get('defaultValue'));
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
