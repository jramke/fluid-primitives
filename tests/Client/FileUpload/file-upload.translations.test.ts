import { describe, expect, test } from 'vitest';
import { resolveTranslations } from '../../../Resources/Private/Primitives/FileUpload/src/file-upload.translations';

const file = (name: string) => new File(['x'], name);

describe('file upload translations', () => {
    test('turns the %fileName% texts into the functions Zag calls and passes the others on', () => {
        const t = resolveTranslations({
            dropzone: 'Drop files here',
            itemPreview: 'Preview of %fileName%',
            deleteFile: 'Remove %fileName% (%fileName%)',
        }) as Record<string, unknown>;

        expect(t.dropzone).toBe('Drop files here');
        expect((t.itemPreview as (file: File) => string)(file('a.png'))).toBe('Preview of a.png');
        expect((t.deleteFile as (file: File) => string)(file('a.png'))).toBe(
            'Remove a.png (a.png)'
        );
    });

    test('leaves an entry that is not a string to the default of Zag instead of calling it', () => {
        const t = resolveTranslations({
            dropzone: false,
            deleteFile: false,
            itemPreview: 'Preview of %fileName%',
        } as never) as Record<string, unknown>;

        expect(Object.keys(t)).toEqual(['itemPreview']);
    });

    test('has nothing to resolve without translations', () => {
        expect(resolveTranslations(undefined)).toBeUndefined();
    });
});
