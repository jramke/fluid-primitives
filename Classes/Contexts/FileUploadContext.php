<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Service\TranslatorService;
use Jramke\FluidPrimitives\Traits\HasTranslationsTrait;
use Jramke\FluidPrimitives\Utility\Typed;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[Autoconfigure(public: true)]
class FileUploadContext extends AbstractComponentContext
{
    use HasTranslationsTrait;

    public function __construct(
        protected readonly TranslatorService $translator,
    ) {}

    protected function getTranslator(): TranslatorService
    {
        return $this->translator;
    }

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
    /**
     * @return array{dropzone: string, itemPreview: string, deleteFile: string}
     */
    public function getTranslations(): array
    {
        return $this->translationsWithDefaults([
            'dropzone' => 'fileUpload.dropzoneLabel',
            'itemPreview' => 'fileUpload.itemPreviewLabel',
            'deleteFile' => 'fileUpload.deleteFileLabel',
        ]);
    }

    /**
     * The `accept` attribute string for the hidden native file input, mirroring zag-js's own
     * `getAcceptAttrString` (`@zag-js/file-utils`) so the server-rendered markup matches what the
     * client re-computes on hydration.
     */
    public function getAcceptAttr(): ?string
    {
        // `accept` is declared type="mixed" and genuinely accepts either shape below - stays mixed
        // until is_string()/is_array() decide which branch narrows it.
        // @mago-expect analysis:mixed-assignment
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

        $tokens = $accept;
        if (!array_is_list($accept)) {
            $tokens = [];
            // $extensions is deliberately left as-is (mixed) here - it's either a single extension
            // string or a list of them, and the (array) cast below needs the original shape;
            // narrowing happens per-extension in the inner loop instead.
            // @mago-expect analysis:mixed-assignment
            foreach ($accept as $mimeType => $extensions) {
                $tokens[] = $mimeType;
                foreach (array_map(Typed::string(...), (array)$extensions) as $extension) {
                    $tokens[] = $extension;
                }
            }
        }

        $stringTokens = array_map(Typed::string(...), $tokens);
        $validTokens = array_values(array_filter($stringTokens, $this->isValidAcceptToken(...)));

        return $validTokens === [] ? null : implode(',', $validTokens);
    }

    private function isValidAcceptToken(string $value): bool
    {
        if (in_array($value, ['audio/*', 'video/*', 'image/*', 'text/*'], strict: true)) {
            return true;
        }

        if (preg_match('#\w+/[-+.\w]+#', $value) === 1) {
            return true;
        }

        return preg_match('/^.*\.\w+$/', $value) === 1;
    }
}
