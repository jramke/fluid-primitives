<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class ClipboardContext extends AbstractComponentContext
{
    public function __construct(
        private readonly TranslatorService $translator,
    ) {}

    #[ExposeToClient]
    public function getTranslations(): array
    {
        $overrides = $this->get('translations') ?? [];

        $defaults = [
            'triggerLabelIdle' => $this->translator->translate('clipboard.triggerLabelIdle', $this->getRequest()),
            'triggerLabelCopied' => $this->translator->translate('clipboard.triggerLabelCopied', $this->getRequest()),
        ];

        return array_merge($defaults, $overrides);
    }
}
