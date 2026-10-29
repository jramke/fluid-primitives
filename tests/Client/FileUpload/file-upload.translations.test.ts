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

    test('writes a file name with a replacement pattern like $& as it is', () => {
        const t = resolveTranslations({ itemPreview: 'Preview of %fileName%' }) as Record<
            string,
            unknown
        >;

        expect((t.itemPreview as (file: File) => string)(file('a$&b$`c.png'))).toBe(
            'Preview of a$&b$`c.png'
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

    test('keeps an itemPreview or deleteFile function from a client script', () => {
        const itemPreview = (file: File) => `Look at ${file.name}`;
        const t = resolveTranslations({ itemPreview }) as Record<string, unknown>;

        expect(t.itemPreview).toBe(itemPreview);
    });

    test('has nothing to resolve without translations', () => {
        expect(resolveTranslations(undefined)).toBeUndefined();
    });
});
