import type { CollectionItem, ListCollection } from '@zag-js/collection';
import * as combobox from '@zag-js/combobox';
import { createFilter } from '@zag-js/i18n-utils';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { getGlobal, getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type { ComboboxFilterHookResult, ComboboxFilterResolver } from '../../Client/src/types';
import type { FieldMachine } from '../Field/src/field.registry';

type ComboboxPrimitiveProps = combobox.Props & {
    /**
     * Set from a `mountControlled` callback's `controlled` flag. When `true` and no
     * `filterResolver` is set via `setFilter()`, the built-in default filter is skipped entirely,
     * leaving the collection exactly as the consumer's own code last set it (e.g. driven by an
     * async search).
     */
    controlled?: boolean;
};

export class Combobox extends FieldAwareComponent<ComboboxPrimitiveProps, combobox.Api> {
    static name = 'combobox';

    private static defaultFilter = createFilter({
        sensitivity: 'base',
        locale: getGlobal('locale'),
    });

    private sourceCollection?: ListCollection;
    private filterResolver: ComboboxFilterResolver | null = null;

    propsWithField(
        props: ComboboxPrimitiveProps,
        fieldMachine: FieldMachine
    ): ComboboxPrimitiveProps {
        return {
            ...props,
            disabled: props.disabled ?? fieldMachine.context.get('disabled'),
            readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
            required: props.required ?? fieldMachine.context.get('required'),
            invalid: props.invalid ?? fieldMachine.context.get('invalid'),
            name: props.name ?? fieldMachine.prop('name'),
        };
    }

    transformProps(props: ComboboxPrimitiveProps) {
        // `collection` is omitted from hydration data entirely when not passed (e.g. a
        // searchUrl-only, purely async combobox) - default to an empty one rather than crashing.
        const collection = getListCollectionFromHydrationData(props.collection ?? { items: [] });

        return {
            ...props,
            get collection() {
                return collection;
            },
        };
    }

    private getSourceCollection() {
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

    private setCollection(collection: ListCollection) {
        this.updateProps({ collection });
    }

    /**
     * Whether built-in filtering (default substring match or a registered `filterResolver`)
     * should be skipped entirely, deferring all collection updates to the consumer (e.g. an async
     * search wired up in userland).
     */
    private hasManualFiltering(): boolean {
        return !!this.userProps?.controlled && !this.filterResolver;
    }

    private resetCollection() {
        if (this.hasManualFiltering()) return;
        this.setCollection(this.getSourceCollection());
    }

    private filterCollection(inputValue: string) {
        if (this.hasManualFiltering()) return;

        const sourceCollection = this.getSourceCollection();
        const query = inputValue.trim();

        if (!query) {
            this.resetCollection();
            return;
        }

        if (this.filterResolver) {
            const result = this.filterResolver({
                inputValue,
                collection: sourceCollection,
                component: this,
            });
            this.setCollection(this.normalizeFilterResult(result, sourceCollection));
            return;
        }

        this.setCollection(
            sourceCollection.filter(itemString =>
                Combobox.defaultFilter.contains(itemString, query)
            )
        );
    }

    public setFilter(filter: ComboboxFilterResolver | null) {
        this.filterResolver = filter;

        const inputValue = this.machine?.context.get('inputValue') || '';
        if (!inputValue.trim()) {
            this.resetCollection();
            return;
        }

        this.filterCollection(inputValue);
    }

    private normalizeFilterResult(
        result: ComboboxFilterHookResult,
        sourceCollection: ListCollection<CollectionItem>
    ): ListCollection<CollectionItem> {
        if (!result) {
            return sourceCollection;
        }

        if (Array.isArray(result)) {
            return sourceCollection.copy(result);
        }

        return result;
    }

    initMachine(props: ComboboxPrimitiveProps): Machine<any> {
        props = this.withFieldProps(props);
        const { controlled, ...transformedProps } = this.transformProps(props);

        return new Machine(combobox.machine, {
            ...transformedProps,
            onInputValueChange: details => {
                if (details.reason === 'input-change') {
                    this.filterCollection(details.inputValue);
                } else if (!details.inputValue.trim()) {
                    this.resetCollection();
                }

                transformedProps.onInputValueChange?.(details);
            },
            onOpenChange: details => {
                const inputValue = (this.machine.context.get('inputValue') || '').trim();

                if (details.open) {
                    if (inputValue === '') {
                        this.resetCollection();
                    } else {
                        this.filterCollection(inputValue);
                    }
                } else {
                    this.resetCollection();
                }

                transformedProps.onOpenChange?.(details);
            },
        });
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

        const inputEl = this.getElement('input');
        if (inputEl) {
            const mergedProps = mergeProps(this.api.getInputProps(), {
                'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
            });
            this.spreadProps(inputEl, mergedProps);
        }

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

        const itemGroupEls = this.getElements('itemGroup');
        itemGroupEls.forEach(itemGroupEl => {
            this.spreadProps(
                itemGroupEl,
                this.api.getItemGroupProps({ id: itemGroupEl.dataset.id! })
            );
            const itemGroupLabelEl = this.getElement('itemGroupLabel', itemGroupEl);
            if (itemGroupLabelEl) {
                this.spreadProps(
                    itemGroupLabelEl,
                    this.api.getItemGroupLabelProps({ htmlFor: itemGroupEl.dataset.id! })
                );
            }
        });

        const sourceCollection = this.getSourceCollection();
        // Returns both the resolved item and whether it came from sourceCollection specifically -
        // the item callback below needs that distinction for its hidden-toggle logic, so it's
        // exposed here instead of collapsed away, letting every callback resolve in one lookup.
        const resolveItem = (value: string) => {
            const sourceItem = sourceCollection.find(value);
            return { sourceItem, item: sourceItem ?? this.api.collection.find(value) };
        };

        this.spreadPropsByValue('item', ({ el, value }) => {
            const { sourceItem, item } = resolveItem(value);
            if (!item) return null;
            // Static/server-rendered items keep the existing sync-filter hide/show behavior.
            // Dynamically-inserted (async) items are only ever in the DOM because they're a
            // current result - never auto-hidden here.
            el.hidden = sourceItem ? !this.api.collection.has(item.value) : false;
            return this.api.getItemProps({ item });
        });

        this.spreadPropsByValue('itemText', ({ value }) => {
            const { item } = resolveItem(value);
            return item ? this.api.getItemTextProps({ item }) : null;
        });

        this.spreadPropsByValue('itemIndicator', ({ value }) => {
            const { item } = resolveItem(value);
            return item ? this.api.getItemIndicatorProps({ item }) : null;
        });

        const itemEls = this.getElements('item');

        itemGroupEls.forEach(itemGroupEl => {
            const hasVisibleItems = this.getElements('item', itemGroupEl).some(
                itemEl => !itemEl.hidden
            );
            itemGroupEl.hidden = !hasVisibleItems;
        });

        const hasVisibleItems = itemEls.some(itemEl => !itemEl.hidden);

        if (contentEl) {
            contentEl.toggleAttribute('data-empty', !hasVisibleItems);
        }

        if (listEl) {
            listEl.toggleAttribute('data-empty', !hasVisibleItems);
        }
    }
}
