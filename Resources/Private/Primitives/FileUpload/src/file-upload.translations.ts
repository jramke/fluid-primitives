import type * as fileUpload from '@zag-js/file-upload';
import { resolveTranslations as resolve, type TranslationText } from '../../../Client';

/**
 * The translations as a template or a client script writes them: plain strings, `itemPreview` and
 * `deleteFile` with a `%fileName%` placeholder or as a function of the `File`.
 */
export type FileUploadTranslationTexts = {
    dropzone?: string;
    itemPreview?: TranslationText<File>;
    deleteFile?: TranslationText<File>;
};

const fileName = (file: File) => ({ fileName: file.name });

/**
 * zag-js's `itemPreview`/`deleteFile` translations are functions (`(file: File) => string`) that
 * interpolate the file's name - but Fluid has no callbacks to hand over, and the actual `File` only
 * ever exists in the browser anyway. `FileUploadContext::getTranslations()` therefore translates
 * them as plain strings containing a literal `%fileName%` placeholder (not `{fileName}` - Fluid's
 * own inline array/object syntax already treats a bare `{...}` inside a string as a nested variable
 * expression, so a curly-brace placeholder breaks the moment someone overrides `translations` from a
 * template); this wraps such a string into the function zag-js actually expects, substituting the
 * placeholder per file. A function from a client script is kept. An entry that is neither is left
 * to Zag's default.
 */
export function resolveTranslations(
    translations: FileUploadTranslationTexts | undefined
): fileUpload.Props['translations'] {
    return resolve<fileUpload.Props['translations']>(translations, {
        itemPreview: fileName,
        deleteFile: fileName,
    });
}
