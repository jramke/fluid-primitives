import type { FileRejection, ItemType } from '@zag-js/file-upload';
import * as fileUpload from '@zag-js/file-upload';
import { isValidFileType } from '@zag-js/file-utils';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps, Template } from '../../Client';
import type { FieldMachine } from '../Field/src/field.registry';

interface ItemEntry {
    file: File;
    type: ItemType;
    errors?: string[];
}

/**
 * `existingFilesCount` is a plain integer (not a list of `File` objects) precisely so the already-
 * persisted files it represents never enter the machine's own `acceptedFiles` - anything that does
 * also ends up in the hidden input's `FileList` (via the machine's own `syncInputElement` action)
 * and gets resubmitted on the next form post, which would silently overwrite an already-stored file
 * with placeholder content reconstructed from just its name/size. Subtracting the count from
 * `maxFiles` instead gets the same "remaining slots" UX (and the machine's own `TOO_MANY_FILES`
 * rejection) without ever touching a real `File`.
 */
type FileUploadPrimitiveProps = fileUpload.Props & { existingFilesCount?: number };

function resolveMaxFiles(props: FileUploadPrimitiveProps): number | undefined {
    const existingFilesCount = props.existingFilesCount ?? 0;
    if (existingFilesCount <= 0 || typeof props.maxFiles !== 'number') return props.maxFiles;

    return Math.max(0, props.maxFiles - existingFilesCount);
}

/**
 * The stable, per-file identity stamped onto every rendered item's `data-value` (and folded into
 * its `id`) - matches zag-js's own `getFileId` intent (`@zag-js/file-upload`), which does the same
 * thing internally but isn't part of that package's public API (only its main entry and `./anatomy`
 * are exported; the `.dom` module `getFileId` lives in isn't), and returns a hash rather than this
 * plain, readable string. Exported so userland code can go from a rendered item's `data-value` back
 * to the real `File` it represents (e.g. `acceptedFiles.find(f => fileValue(f) === value)`) without
 * reimplementing this exact format - see the FileUpload "Custom Item Layout" docs example.
 */
export function fileValue(file: File): string {
    return `${file.name}-${file.size}`;
}

/**
 * Whether `file` should use the `itemPreview` variant tagged with `pattern` (e.g. `image/*` or
 * `.pdf`). `.*`/`*`/empty are our own catch-all sentinel for a fallback variant - zag-js's own
 * `isValidFileType` (`@zag-js/file-utils`) has no such "match everything" concept (a leading-dot
 * pattern there means a literal file extension), so that case is handled before delegating.
 * Otherwise reuses `isValidFileType`'s own accept-string matching (wildcard MIME types, exact MIME
 * types, and file extensions) instead of re-implementing it.
 */
function matchesFileType(pattern: string | undefined, file: File): boolean {
    if (!pattern || pattern === '.*' || pattern === '*') return true;
    return isValidFileType(file, pattern)[0];
}

function fileExtension(fileName: string): string {
    const dotIndex = fileName.lastIndexOf('.');
    return dotIndex === -1 ? '' : fileName.slice(dotIndex + 1).toUpperCase();
}

const FILE_NAME_PLACEHOLDER = '%fileName%';

/**
 * zag-js's `itemPreview`/`deleteFile` translations are functions (`(file: File) => string`) that
 * interpolate the file's name - but Fluid has no callbacks to hand over, and the actual `File` only
 * ever exists in the browser anyway. `FileUploadContext::getTranslations()` therefore translates
 * them as plain strings containing a literal `%fileName%` placeholder (not `{fileName}` - Fluid's
 * own inline array/object syntax already treats a bare `{...}` inside a string as a nested variable
 * expression, so a curly-brace placeholder breaks the moment someone overrides `translations` from a
 * template); this wraps such a string into the function zag-js actually expects, substituting the
 * placeholder per file. `false` (Root's documented way to omit a translation's `aria-label`/`alt`
 * entirely) is turned into a function returning `undefined` instead of being left as a bare `false`
 * - zag-js calls these unconditionally with optional chaining (`translations.deleteFile?.(file)`),
 * which only guards against `null`/`undefined` and would otherwise try to call `false` as a
 * function. Any other non-string value (e.g. a real function passed in from an entry.ts) is left
 * untouched.
 */
function resolveTranslations(
    translations: Record<string, unknown> | undefined
): fileUpload.Props['translations'] {
    if (!translations) return undefined;

    const resolved: Record<string, unknown> = { ...translations };
    for (const key of ['itemPreview', 'deleteFile'] as const) {
        const template = resolved[key];
        if (typeof template === 'string') {
            resolved[key] = (file: File) => template.replaceAll(FILE_NAME_PLACEHOLDER, file.name);
        } else if (template === false) {
            resolved[key] = () => undefined;
        }
    }

    return resolved as fileUpload.Props['translations'];
}

export class FileUpload extends FieldAwareComponent<FileUploadPrimitiveProps, fileUpload.Api> {
    static componentName = 'file-upload';

    private previewCleanups = new Map<Element, () => void>();
    private lastAcceptedFiles: File[] | null = null;
    private lastRejectedFiles: FileRejection[] | null = null;

    propsWithField(
        props: FileUploadPrimitiveProps,
        fieldMachine: FieldMachine
    ): FileUploadPrimitiveProps {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    initMachine(props: FileUploadPrimitiveProps): Machine<any> {
        props = this.withFieldProps(props);
        return new Machine(fileUpload.machine, {
            ...props,
            maxFiles: resolveMaxFiles(props),
            translations: resolveTranslations(
                props.translations as Record<string, unknown> | undefined
            ),
        });
    }

    initApi() {
        return fileUpload.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const dropzoneEl = this.getElement('dropzone');
        if (dropzoneEl) this.spreadProps(dropzoneEl, this.api.getDropzoneProps());

        const triggerEl = this.getElement('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

        const hiddenInputEl = this.getElement<HTMLInputElement>('hiddenInput');
        if (hiddenInputEl) {
            const mergedProps = mergeProps(this.api.getHiddenInputProps(), {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(hiddenInputEl, mergedProps);
        }

        const clearTriggerEl = this.getElement('clearTrigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        this.renderItemGroups();
    }

    private renderItemGroups() {
        const acceptedFiles = this.api.acceptedFiles;
        const rejectedFiles = this.api.rejectedFiles;

        const filesChanged =
            acceptedFiles !== this.lastAcceptedFiles || rejectedFiles !== this.lastRejectedFiles;

        const itemGroupEls = this.getElements<HTMLElement>('itemGroup');
        itemGroupEls.forEach(itemGroupEl => {
            const type = (itemGroupEl.dataset.value as ItemType) || 'accepted';
            this.spreadProps(itemGroupEl, this.api.getItemGroupProps({ type }));

            // Existing (already-persisted) items are authored directly in Fluid rather than
            // populated from the machine's File-based acceptedFiles/rejectedFiles - wire their
            // delete behavior once, independent of the files-changed check below.
            this.wireExistingItemDeleteTriggers(itemGroupEl);

            if (filesChanged) {
                const entries: ItemEntry[] =
                    type === 'rejected'
                        ? rejectedFiles.map(({ file, errors }) => ({ file, type, errors }))
                        : acceptedFiles.map(file => ({ file, type }));

                this.renderItems(itemGroupEl, entries, type);
            }

            this.updateEmptyState(itemGroupEl);
        });

        this.lastAcceptedFiles = acceptedFiles;
        this.lastRejectedFiles = rejectedFiles;
    }

    /**
     * An `itemGroup` is "empty" only once none of its items - machine-managed or existing - are
     * visible, so an edit form pre-populated with existing files never shows the empty state
     * underneath them.
     */
    private updateEmptyState(itemGroupEl: HTMLElement) {
        const emptyStateEl = itemGroupEl.querySelector<HTMLElement>('[data-part="empty-state"]');
        if (!emptyStateEl) return;

        const hasVisibleItems = Array.from(
            itemGroupEl.querySelectorAll<HTMLElement>('[data-part="item"]')
        ).some(el => !el.hidden);

        emptyStateEl.hidden = hasVisibleItems;
    }

    /**
     * Existing items carry their own `ui:fileUploadDeleteCheckbox` (unchecked, hidden) as a
     * sibling of their `itemDeleteTrigger` button - not nested inside it, since a native `<button>`
     * may not contain interactive descendants like `<input>`. Clicking the trigger checks that box
     * (so the deletion is applied on the next form submission) and hides the item, mirroring how a
     * machine-managed item disappears on delete. Wiring is idempotent so it's safe to call on every
     * render.
     */
    private wireExistingItemDeleteTriggers(itemGroupEl: HTMLElement) {
        const existingItemEls = itemGroupEl.querySelectorAll<HTMLElement>(
            '[data-part="item"][data-type="existing"]:not([data-file-upload-wired])'
        );

        existingItemEls.forEach(itemEl => {
            itemEl.dataset.fileUploadWired = 'true';

            const checkboxEl = itemEl.querySelector<HTMLInputElement>('input[type="checkbox"]');
            const deleteTriggerEl = itemEl.querySelector<HTMLElement>(
                '[data-part="item-delete-trigger"]'
            );
            if (!checkboxEl || !deleteTriggerEl) return;

            deleteTriggerEl.addEventListener('click', () => {
                if (this.api.disabled || this.api.readOnly) return;
                checkboxEl.checked = true;
                itemEl.hidden = true;
                this.updateEmptyState(itemGroupEl);
            });
        });
    }

    /**
     * `rejectedItemTemplate` is an optional second `ui:template`, letting a consumer author
     * completely different markup for rejected items (e.g. no preview, shown in its own list below
     * the accepted files) instead of reusing `itemTemplate` for both - see the "Custom Item Layout"
     * docs example. Falls back to `itemTemplate` when it isn't present, so existing single-template
     * consumers are unaffected.
     */
    private resolveItemTemplatePart(type: ItemType): string {
        if (type === 'rejected' && this.getElement('rejectedItemTemplate')) {
            return 'rejectedItemTemplate';
        }
        return 'itemTemplate';
    }

    private renderItems(itemGroupEl: HTMLElement, entries: ItemEntry[], type: ItemType) {
        itemGroupEl
            .querySelectorAll<HTMLElement>('[data-part="item"]:not([data-type="existing"])')
            .forEach(el => {
                this.previewCleanups.get(el)?.();
                this.previewCleanups.delete(el);
                el.remove();
            });

        if (!this.hydrator) return;

        const templatePart = this.resolveItemTemplatePart(type);

        entries.forEach(({ file, type, errors }) => {
            const instance = new Template(this.hydrator!, templatePart, {
                value: fileValue(file),
            });
            const itemEl = instance.root;

            this.spreadProps(itemEl, this.api.getItemProps({ file, type }));

            const errorEl = instance.getElement<HTMLElement>('itemError');
            if (errorEl) {
                errorEl.hidden = !errors?.length;
                errorEl.textContent = errors?.join(', ') ?? '';
            }

            const nameEl = instance.getElement<HTMLElement>('itemName');
            if (nameEl) {
                this.spreadProps(nameEl, this.api.getItemNameProps({ file, type }));
                nameEl.textContent = file.name;
            }

            const sizeEl = instance.getElement<HTMLElement>('itemSizeText');
            if (sizeEl) {
                this.spreadProps(sizeEl, this.api.getItemSizeTextProps({ file, type }));
                sizeEl.textContent = this.api.getFileSize(file);
            }

            this.renderPreview(instance, file, type);

            const deleteTriggerEl = instance.getElement<HTMLElement>('itemDeleteTrigger');
            if (deleteTriggerEl) {
                this.spreadProps(
                    deleteTriggerEl,
                    this.api.getItemDeleteTriggerProps({ file, type })
                );
            }

            itemGroupEl.appendChild(instance);
        });
    }

    private renderPreview(instance: Template, file: File, type: ItemType) {
        const previews = instance.getElements<HTMLElement>('itemPreview');
        if (previews.length === 0) return;

        const matched =
            previews.find(el => matchesFileType(el.dataset.match, file)) ??
            previews[previews.length - 1];

        previews.forEach(el => {
            const isMatch = el === matched;
            el.hidden = !isMatch;
            if (!isMatch) return;

            this.spreadProps(el, this.api.getItemPreviewProps({ file, type }));

            const fallbackEl = el.querySelector<HTMLElement>('[data-part="item-preview-fallback"]');
            if (fallbackEl) fallbackEl.textContent = fileExtension(file.name);
        });

        if (!matched) return;

        const imageEl = matched.querySelector<HTMLImageElement>('[data-part="item-preview-image"]');
        if (!imageEl || !file.type.startsWith('image/')) return;

        const cleanup = this.api.createFileUrl(file, url => {
            this.spreadProps(imageEl, this.api.getItemPreviewImageProps({ file, url, type }));
        });
        this.previewCleanups.set(matched, cleanup);
    }

    destroy() {
        this.previewCleanups.forEach(cleanup => cleanup());
        this.previewCleanups.clear();
        super.destroy();
    }
}
