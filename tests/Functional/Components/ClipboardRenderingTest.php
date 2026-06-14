<?php

declare(strict_types=1);

use Jramke\FluidPrimitives\Registry\HydrationRegistry;

describe('Clipboard Component Rendering', function () {
    beforeEach(function () {
        HydrationRegistry::getInstance()->clear();
    });

    describe('translations', function () {
        it('renders english trigger label by default', function () {
            $html = $this->renderTemplate('
                <primitives:clipboard.root value="Copy me">
                    <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
                </primitives:clipboard.root>
            ');

            expect($html)->toContain('aria-label="Copy to clipboard"');
        });

        it('renders german trigger label when locale is german', function () {
            $this->setRequestLocale('de_DE');

            $html = $this->renderTemplate('
                <primitives:clipboard.root value="Copy me">
                    <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
                </primitives:clipboard.root>
            ');

            expect($html)->toContain('aria-label="In Zwischenablage kopieren"');
        });

        it('prefers prop overrides over localized defaults', function () {
            $this->setRequestLocale('de_DE');

            $html = $this->renderTemplate('
                <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: \'Link kopieren\'}">
                    <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
                </primitives:clipboard.root>
            ');

            expect($html)->toContain('aria-label="Link kopieren"');
        });

        it('allows disabling trigger aria labels', function () {
            $html = $this->renderTemplate('
                <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: false, triggerLabelCopied: false}">
                    <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
                </primitives:clipboard.root>
            ');

            expect($html)->not->toContain('aria-label=');
        });

        it('includes merged translations in hydration data', function () {
            $this->setRequestLocale('de_DE');

            $this->renderTemplate('
                <primitives:clipboard.root value="Copy me" translations="{triggerLabelIdle: \'Link kopieren\', triggerLabelCopied: false}">
                    <primitives:clipboard.trigger>Copy</primitives:clipboard.trigger>
                </primitives:clipboard.root>
            ');

            $hydrationData = HydrationRegistry::getInstance()->getAll();
            $clipboardData = array_values($hydrationData['clipboard'])[0];

            expect($clipboardData['props']['translations'])->toBe([
                'triggerLabelIdle' => 'Link kopieren',
                'triggerLabelCopied' => false,
            ]);
        });
    });
});
