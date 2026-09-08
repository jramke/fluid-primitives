<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class FileUploadContext extends AbstractComponentContext
{
    public function __construct(
        protected readonly TranslatorService $translator,
    ) {}

    /**
     * `itemPreview`/`deleteFile` are functions in zag-js (`(file: File) => string`), since they're
     * meant to interpolate the file's name - but Fluid/PHP has no callbacks to hand over, and the
     * actual `File` only exists in the browser anyway. So these two are instead translated here as
     * plain strings containing a literal `%fileName%` placeholder, and `FileUpload.ts` wraps that
     * string into the function zag-js expects, substituting the placeholder per file at render time.
     * Deliberately `%fileName%`, not `{fileName}` - Fluid's own inline array/object syntax already
     * treats a bare `{...}` inside a string as a nested variable expression, so a curly-brace
     * placeholder would silently get stripped the moment someone overrides `translations` from a
     * template (e.g. `translations="{deleteFile: 'Remove {fileName}'}"`).
     */
    #[ExposeToClient]
    public function getTranslations(): array
    {
        $overrides = $this->get('translations') ?? [];

        $defaults = [
            'dropzone' => $this->translator->translate('fileUpload.dropzoneLabel', $this->getRequest()),
            'itemPreview' => $this->translator->translate('fileUpload.itemPreviewLabel', $this->getRequest()),
            'deleteFile' => $this->translator->translate('fileUpload.deleteFileLabel', $this->getRequest()),
        ];

        return array_merge($defaults, $overrides);
    }

    /**
     * The `accept` attribute string for the hidden native file input, mirroring zag-js's own
     * `getAcceptAttrString` (`@zag-js/file-utils`) so the server-rendered markup matches what the
     * client re-computes on hydration.
     */
    public function getAcceptAttr(): ?string
    {
        $accept = $this->get('accept');

        if ($accept === null || $accept === '') {
            return null;
        }

        if (is_string($accept)) {
            return $accept;
        }

        if (!is_array($accept)) {
            return null;
        }

        if (array_is_list($accept)) {
            $tokens = $accept;
        } else {
            $tokens = [];
            foreach ($accept as $mimeType => $extensions) {
                $tokens[] = $mimeType;
                foreach ((array)$extensions as $extension) {
                    $tokens[] = $extension;
                }
            }
        }

        $validTokens = array_values(array_filter($tokens, static fn($candidate): bool => self::isValidAcceptToken(
            (string)$candidate,
        )));

        return $validTokens === [] ? null : implode(',', $validTokens);
    }

    private static function isValidAcceptToken(string $value): bool
    {
        if (in_array($value, ['audio/*', 'video/*', 'image/*', 'text/*'], true)) {
            return true;
        }

        if (preg_match('#\w+/[-+.\w]+#', $value) === 1) {
            return true;
        }

        return preg_match('/^.*\.\w+$/', $value) === 1;
    }
}
