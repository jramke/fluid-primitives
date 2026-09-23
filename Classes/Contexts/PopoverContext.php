<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class PopoverContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

    public function getState(): string
    {
        return $this->get('defaultOpen') ? 'open' : 'closed';
    }

    #[ExposeToClient]
    /**
     * @return array{closeTriggerLabel: string}
     */
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'closeTriggerLabel' => 'popover.closeTriggerLabel',
        ]);
    }
}
