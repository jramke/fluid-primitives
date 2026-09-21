import type { CollectionItem, ListCollection } from '@zag-js/collection';
import * as combobox from '@zag-js/combobox';
import { visuallyHiddenStyle } from '@zag-js/dom-query';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { registerClientPropConverters } from '../../Client/src/lib/client-prop-converters';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type { ListCollectionData } from '../../Client/src/types';
import type { FieldMachine } from '../Field/src/field.registry';
export type { ComboboxHydrationProps } from './Combobox.hydration';

// Runs once, at import time, before any mountAll()/mount() call - see Select's own registration
// for the ordering guarantee this relies on. Unlike Select, `collection` is omitted from hydration
// data entirely when not passed (e.g. a searchUrl-only, purely async combobox), so the converter
// itself supplies the empty-collection default rather than delegating straight to
// getListCollectionFromHydrationData, which doesn't handle `undefined`.
registerClientPropConverters('combobox', {
    collection: (collection: ListCollectionData | undefined) =>
        getListCollectionFromHydrationData(collection ?? { items: [] }),
});

declare module 'fluid-primitives/client' {
    interface HydrationPropsOverrides {
        combobox: { collection: combobox.Props['collection'] };
    }
}

export class Combobox extends FieldAwareComponent<combobox.Props, combobox.Api> {
    static componentName = 'combobox';

    private sourceCollection?: ListCollection<any>;

    propsWithField(props: combobox.Props, fieldMachine: FieldMachine): combobox.Props {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    transformProps(props: combobox.Props) {
        return {
            ...props,
            // when selecting an item for example when the suggestions list is opened by the toggle there is no input/change event dispatched,
            // but thats needed for our form to update the formdata and validation
            // we use the change event because the input event opens the suggestions list again
            onSelect: details => {
                this.getElement('input')?.dispatchEvent(new Event('change', { bubbles: true }));
                props?.onSelect?.(details);
            },
        };
    }

    /**
     * The full, unfiltered collection as it was first passed in - `render()` needs it to tell a
     * static/server-rendered item apart from a dynamically-inserted (async) one (see the item
     * callback below), and a consumer's own `onInputValueChange` needs it as the base to filter
     * from or reset to.
     */
    getSourceCollection<T extends CollectionItem = CollectionItem>(): ListCollection<T> {
        if (this.sourceCollection) {
            return this.sourceCollection;
        }

        const initialCollection = this.userProps?.collection;
        if (!initialCollection) {
            throw new Error('Combobox source collection is not available.');
        }

        this.sourceCollection = initialCollection;
        return this.sourceCollection;
    }

    private getHiddenInputProps(
        value: string,
        attrs: { name?: string; form?: string; disabled?: boolean }
    ) {
        return normalizeProps.input({
            type: 'text',
            'aria-hidden': true,
            tabIndex: -1,
            style: visuallyHiddenStyle,
            name: attrs.name,
            form: attrs.form,
            disabled: attrs.disabled,
            value,
            // mirrors `@zag-js/select`'s own `getHiddenSelectProps()`.
            onFocus: () => {
                this.getElement<HTMLInputElement>('input')?.focus({ preventScroll: true });
            },
        });
    }

    // This should be a zag-js native feature, but since it's currently not, we implement it here.
    // Ensures the FormData gets the real value of the collection item(s) rather than the visible input's label.
    // See https://github.com/chakra-ui/zag/discussions/3333
    private syncHiddenInput(attrs: { name?: string; form?: string; disabled?: boolean }) {
        const rootEl = this.getElement('root');
        if (!rootEl) return;

        this.getElements<HTMLInputElement>('hiddenInput').forEach(el => el.remove());

        const values = this.api.value;

        // With `allowCustomValue`, the input can hold free text that never resolved to a
        // collection item - carry that raw text through as the submitted value instead of
        // silently dropping it. Mirrors the machine's own (internal, unexposed) `isCustomValue`
        // computation: the input's text no longer matches what the current selection stringifies to.
        const inputValue = this.api.inputValue;
        const isCustomValue =
            !!this.userProps?.allowCustomValue &&
            inputValue.trim() !== '' &&
            inputValue !== this.api.valueAsString;

        const resolvedValues = values.length > 0 ? values : isCustomValue ? [inputValue] : [];

        resolvedValues.forEach(value => {
            const inputEl = this.doc.createElement('input');
            this.hydrator?.setRefAttributes(inputEl, 'hiddenInput', value);
            this.spreadProps(inputEl, this.getHiddenInputProps(value, attrs));
            rootEl.appendChild(inputEl);
        });
    }

    initMachine(props: combobox.Props): Machine<any> {
        props = this.withFieldProps(props);
        const transformedProps = this.transformProps(props);

        return new Machine(combobox.machine, transformedProps);
    }

    initApi() {
        return combobox.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.getElement('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.getElement('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.getElement('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        // `name`/`form` are stripped from the visible input's props before spreading - it holds the
        // item's label (or, with `allowCustomValue`, arbitrary free text), never the underlying
        // value the form should submit. `hiddenInput` carries the real value(s) instead, see below.
        const { name, form, ...inputProps } = this.api.getInputProps() as Record<string, unknown>;

        const inputEl = this.getElement('input');
        if (inputEl) {
            const mergedProps = mergeProps(inputProps, {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(inputEl, mergedProps);
        }

        this.syncHiddenInput({
            name: name as string | undefined,
            form: form as string | undefined,
            disabled: inputProps.disabled as boolean | undefined,
        });

        const triggerEl = this.getElement('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

        const clearTriggerEl = this.getElement('clearTrigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        const positionerEl = this.getElement('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const listEl = this.getElement('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        this.spreadPropsByValue(
            'itemGroup',
            ({ value }) => {
                return this.api.getItemGroupProps({ id: value });
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'itemGroupLabel',
            ({ value }) => {
                return this.api.getItemGroupLabelProps({ htmlFor: value });
            },
            { parent: this.doc }
        );

        const sourceCollection = this.getSourceCollection();
        // Returns both the resolved item and whether it came from sourceCollection specifically -
        // the item callback below needs that distinction for its hidden-toggle logic, so it's
        // exposed here instead of collapsed away, letting every callback resolve in one lookup.
        const resolveItem = (value: string) => {
            const sourceItem = sourceCollection.find(value);
            return { sourceItem, item: sourceItem ?? this.api.collection.find(value) };
        };

        this.spreadPropsByValue(
            'item',
            ({ el, value }) => {
                const { sourceItem, item } = resolveItem(value);
                if (!item) return null;
                // Static/server-rendered items keep the existing sync-filter hide/show behavior.
                // Dynamically-inserted (async) items are only ever in the DOM because they're a
                // current result - never auto-hidden here.
                el.hidden = sourceItem ? !this.api.collection.has(item.value) : false;
                return this.api.getItemProps({ item });
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'itemText',
            ({ value }) => {
                const { item } = resolveItem(value);
                return item ? this.api.getItemTextProps({ item }) : null;
            },
            { parent: this.doc }
        );

        this.spreadPropsByValue(
            'itemIndicator',
            ({ value }) => {
                const { item } = resolveItem(value);
                return item ? this.api.getItemIndicatorProps({ item }) : null;
            },
            { parent: this.doc }
        );

        const itemGroupEls = this.getElements('itemGroup', this.doc);
        itemGroupEls.forEach(itemGroupEl => {
            const hasVisibleItems = this.getElements('item', itemGroupEl).some(
                itemEl => !itemEl.hidden
            );
            itemGroupEl.hidden = !hasVisibleItems;
        });

        const itemEls = this.getElements('item', this.doc);
        const hasVisibleItems = itemEls.some(itemEl => !itemEl.hidden);

        if (contentEl) {
            contentEl.toggleAttribute('data-empty', !hasVisibleItems);
        }

        if (listEl) {
            listEl.toggleAttribute('data-empty', !hasVisibleItems);
        }

        const emptyEl = this.getElement('empty');
        if (emptyEl) emptyEl.hidden = hasVisibleItems;
    }
}
