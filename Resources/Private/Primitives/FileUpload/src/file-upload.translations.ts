import type * as fileUpload from '@zag-js/file-upload';

const FILE_NAME_PLACEHOLDER = '%fileName%';

/** The translations as PHP ships them: plain strings, `itemPreview` and `deleteFile` with a `%fileName%` placeholder. */
export type FileUploadTranslationTexts = {
    dropzone?: string;
    itemPreview?: string;
    deleteFile?: string;
};

/**
 * zag-js's `itemPreview`/`deleteFile` translations are functions (`(file: File) => string`) that
 * interpolate the file's name - but Fluid has no callbacks to hand over, and the actual `File` only
 * ever exists in the browser anyway. `FileUploadContext::getTranslations()` therefore translates
 * them as plain strings containing a literal `%fileName%` placeholder (not `{fileName}` - Fluid's
 * own inline array/object syntax already treats a bare `{...}` inside a string as a nested variable
 * expression, so a curly-brace placeholder breaks the moment someone overrides `translations` from a
 * template); this wraps such a string into the function zag-js actually expects, substituting the
 * placeholder per file. An entry that is not a string is left to Zag's default.
 */
export function resolveTranslations(
    translations: FileUploadTranslationTexts | undefined
): fileUpload.Props['translations'] {
    if (!translations) return undefined;

    const resolved: Record<string, unknown> = {};
    for (const [key, text] of Object.entries(translations)) {
        if (typeof text !== 'string') continue;
        resolved[key] =
            key === 'itemPreview' || key === 'deleteFile'
                ? (file: File) => text.replaceAll(FILE_NAME_PLACEHOLDER, () => file.name)
                : text;
    }

    return resolved as fileUpload.Props['translations'];
}
