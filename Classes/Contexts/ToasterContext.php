<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;

class ToasterContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    /**
     * @return array{groupLabel: string, closeTriggerLabel: string}
     */
    #[ExposeToClient]
    public function getTranslations(): array
    {
        // translationsWithDefaults() merges user overrides, so it can't be typed more precisely than array.
        // @mago-expect analysis:invalid-return-statement
        return $this->translationsWithDefaults([
            'groupLabel' => 'toaster.groupLabel',
            'closeTriggerLabel' => 'toaster.closeTriggerLabel',
        ]);
    }
}
