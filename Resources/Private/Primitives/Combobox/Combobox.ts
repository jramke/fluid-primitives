import type { CollectionItem, ListCollection } from '@zag-js/collection';
import * as combobox from '@zag-js/combobox';
import { createFilter } from '@zag-js/i18n-utils';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { getGlobal, getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import type {
    ComboboxFilterHook,
    ComboboxFilterHookResult,
    ComboboxFilterResolver,
} from '../../Client/src/types';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

type ComboboxPrimitiveProps = combobox.Props & {
    filterHook?: string;
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
            ids: {
                ...props.ids,
                label: fieldDom.getLabelId(fieldMachine.scope),
                input: fieldDom.getControlId(fieldMachine.scope),
            },
        };
    }

    transformProps(props: ComboboxPrimitiveProps) {
        const collection = getListCollectionFromHydrationData(props.collection!);

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

    private resetCollection() {
        this.setCollection(this.getSourceCollection());
    }

    private filterCollection(inputValue: string) {
        const sourceCollection = this.getSourceCollection();
        const query = inputValue.trim();

        if (!query) {
            this.resetCollection();
            return;
        }

        const filterHook = this.filterResolver ?? this.getFilterHook(this.userProps?.filterHook);
        if (filterHook) {
            const result = filterHook({
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

    private getFilterHook(hookName: unknown): ComboboxFilterHook | null {
        if (typeof hookName !== 'string' || hookName === '') {
            return null;
        }

        return window.FluidPrimitives.hooks?.combobox?.filters?.[hookName] ?? null;
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
        const transformedProps = this.transformProps(props);

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

        const clearTriggerEl = this.getElement('clear-trigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        const positionerEl = this.getElement('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const listEl = this.getElement('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        const itemGroupEls = this.getElements('item-group');
        itemGroupEls.forEach(itemGroupEl => {
            this.spreadProps(
                itemGroupEl,
                this.api.getItemGroupProps({ id: itemGroupEl.dataset.id! })
            );
            const itemGroupLabelEl = this.getElement('item-group-label', itemGroupEl);
            if (itemGroupLabelEl) {
                this.spreadProps(
                    itemGroupLabelEl,
                    this.api.getItemGroupLabelProps({ htmlFor: itemGroupEl.dataset.id! })
                );
            }
        });

        const itemEls = this.getElements('item');
        const sourceCollection = this.getSourceCollection();
        itemEls.forEach(itemEl => {
            const item = sourceCollection.find(itemEl.dataset.value);
            if (item) {
                itemEl.hidden = !this.api.collection.has(item.value);
                this.spreadProps(itemEl, this.api.getItemProps({ item }));
            }
        });

        const itemTextEls = this.getElements('item-text');
        itemTextEls.forEach(itemTextEl => {
            const item = sourceCollection.find(itemTextEl.dataset.value);
            if (item) {
                this.spreadProps(itemTextEl, this.api.getItemTextProps({ item }));
            }
        });

        const itemIndicatorEls = this.getElements('item-indicator');
        itemIndicatorEls.forEach(itemIndicatorEl => {
            const item = sourceCollection.find(itemIndicatorEl.dataset.value);
            if (item) {
                this.spreadProps(itemIndicatorEl, this.api.getItemIndicatorProps({ item }));
            }
        });

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
