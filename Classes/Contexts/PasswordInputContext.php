<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasIndicatorStateTrait;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;

class PasswordInputContext extends AbstractComponentContext
{
    use HasIndicatorStateTrait;
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
        return Typed::bool($this->get('defaultVisible')) ? 'visible' : 'hidden';
    }

    /**
     * @return array{visibilityTriggerLabelHidden: string, visibilityTriggerLabelVisible: string}
     */
    #[ExposeToClient]
    public function getTranslations(): array
    {
        // translationsWithDefaults() merges user overrides, so it can't be typed more precisely than array.
        // @mago-expect analysis:invalid-return-statement
        return $this->translationsWithDefaults([
            'visibilityTriggerLabelHidden' => 'passwordInput.visibilityTriggerLabelHidden',
            'visibilityTriggerLabelVisible' => 'passwordInput.visibilityTriggerLabelVisible',
        ]);
    }

    public function getVisibilityTriggerLabel(): string
    {
        $translations = $this->getTranslations();

        return $this->getState() === 'visible'
            ? $translations['visibilityTriggerLabelVisible']
            : $translations['visibilityTriggerLabelHidden'];
    }
}
