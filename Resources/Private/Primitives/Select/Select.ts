import type { CollectionItem, ListCollection } from '@zag-js/collection';
import * as select from '@zag-js/select';
import {
    FieldAwareComponent,
    Machine,
    mergeProps,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/list-collection';
import type { FieldClientApi } from '../Field/src/field.handle';
import { resolveTranslations } from './src/select.translations';

// Wire shape -> real @zag-js/collection ListCollection instance. Registered here (not in
// transformProps) so mountAll/mount convert it before the component is even constructed - see
// client-prop-converters.ts. The override type below is derived from this const via `typeof`
// rather than hand-typed a second time, so the two can't drift.
const selectPropConverters = {
    collection: (collection: Parameters<typeof getListCollectionFromHydrationData>[0]) =>
        getListCollectionFromHydrationData(collection),
    // PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
    // prop (see WireTypeResolver), so `positioning` resolves to `unknown` on the wire - this just
    // tells TS what it actually is (a real @zag-js/popper PositioningOptions object).
    positioning: (positioning: select.Props['positioning']) => positioning,
    // `itemAnnouncement` is a function in Zag, a text with placeholders on the wire.
    translations: resolveTranslations,
} satisfies ClientPropConverterMap;

registerClientPropConverters('select', selectPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        select: ConverterMachineProps<typeof selectPropConverters>;
    }
}

/** The props of the machine and the callbacks of the component around it. */
export interface SelectProps extends select.Props {
    /**
     * Called with the text of the filter `input` whenever it changes, and with `''` when the select closes.
     * Filter the collection from {@link Select.getSourceCollection} and pass it to `updateProps`.
     */
    onInputValueChange?: (details: { inputValue: string }) => void;
}

export class Select extends FieldAwareComponent<SelectProps, select.Api> {
    static componentName = 'select';

    private sourceCollection?: ListCollection<any>;

    /**
     * The full, unfiltered collection as it was first passed in. A filter starts from it, and `render()`
     * tells the items the current collection no longer holds from it to hide them.
     *
     * @returns The collection the select was created with.
     */
    getSourceCollection<T extends CollectionItem = CollectionItem>(): ListCollection<T> {
        this.sourceCollection ??= this.machine.prop('collection');
        return this.sourceCollection as ListCollection<T>;
    }

    transformProps(props: SelectProps): SelectProps {
        return {
            ...props,
            onOpenChange: details => {
                if (!details.open) this.resetFilter();
                props.onOpenChange?.(details);
            },
        };
    }

    init() {
        super.init();
        this.hydrator.query<HTMLInputElement>('input')?.addEventListener('input', event => {
            this.userProps.onInputValueChange?.({
                inputValue: (event.target as HTMLInputElement).value,
            });
        });
        return this;
    }

    private resetFilter() {
        const inputEl = this.hydrator.query<HTMLInputElement>('input');
        if (!inputEl || inputEl.value === '') return;
        inputEl.value = '';
        this.userProps.onInputValueChange?.({ inputValue: '' });
    }

    propsWithField(props: SelectProps, field: FieldClientApi): SelectProps {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            required: props.required ?? field.required,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: SelectProps): Machine<any> {
        const [machineProps] = select.splitProps(this.withFieldProps(props));
        return new Machine(select.machine, machineProps);
    }

    initApi() {
        return select.connect(this.machine.service, normalizeProps);
    }

    render = () => {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const hiddenSelectEl = this.hydrator.query('hiddenSelect');
        if (hiddenSelectEl) {
            // Zag selects the options itself. Its `value` here would select the placeholder option of an
            // empty select again, and a form submits that option instead of leaving the select out.
            const { value, ...hiddenSelectProps } = this.api.getHiddenSelectProps();
            this.spreadProps(hiddenSelectEl, hiddenSelectProps);
        }

        // Zag leaves an empty select without a selected option, which a form leaves out: the empty-value
        // input submits the name then.
        this.syncEmptyValueInput(this.api.value.length > 0, this.api.disabled);

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        // The trigger is what receives focus, the hidden select is aria-hidden: the field's helper
        // and error texts have to be described from the trigger to be announced at all.
        const triggerEl = this.hydrator.query('trigger');
        if (triggerEl) {
            const mergedProps = mergeProps(this.api.getTriggerProps(), {
                'aria-describedby': this.field?.ariaDescribedby,
            });
            this.spreadProps(triggerEl, mergedProps);
        }

        const positionerEl = this.hydrator.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const contentEl = this.hydrator.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const inputEl = this.hydrator.query('input');
        if (inputEl) this.spreadProps(inputEl, this.api.getInputProps());

        const listEl = this.hydrator.query('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        // We need to make sure the element is rerendered because otherwise safari doesnt update the spans value in the a11y tree
        // and the button would announce an old value when it receives focus.
        // see: https://github.com/chakra-ui/zag/issues/3099
        const valueTextEl = this.hydrator.query('valueText');
        if (valueTextEl) {
            const currentText = valueTextEl.textContent || valueTextEl.dataset.placeholder || '';
            const nextValue = this.api.valueAsString || valueTextEl.dataset.placeholder || '';

            this.spreadProps(valueTextEl, this.api.getValueTextProps());
            // Text, never `children`: Zag writes that with innerHTML, and a label is user data
            if (valueTextEl.textContent !== nextValue) valueTextEl.textContent = nextValue;

            if (nextValue !== currentText) {
                queueMicrotask(() => {
                    const el = this.hydrator.query('valueText');
                    if (el?.isConnected) {
                        const next = el.cloneNode(true) as HTMLElement;
                        el.replaceWith(next);
                        next.replaceWith(el);
                    }
                });
            }
        }

        this.spreadPropsByValue('itemGroup', ({ value }) => {
            return this.api.getItemGroupProps({ id: value });
        });

        this.spreadPropsByValue('itemGroupLabel', ({ value }) => {
            return this.api.getItemGroupLabelProps({ htmlFor: value });
        });

        const sourceCollection = this.getSourceCollection();
        this.spreadPropsByValue('item', ({ el, value }) => {
            const item = sourceCollection.find(value);
            if (!item) return null;
            // An item the filtered collection no longer holds stays in the DOM, hidden
            el.hidden = !this.api.collection.has(value);
            return this.api.getItemProps({ item });
        });

        this.spreadPropsByValue('itemText', ({ value }) => {
            const item = this.api.collection.find(value);
            return item ? this.api.getItemTextProps({ item }) : null;
        });

        this.spreadPropsByValue('itemIndicator', ({ value }) => {
            const item = this.api.collection.find(value);
            return item ? this.api.getItemIndicatorProps({ item }) : null;
        });

        this.hydrator.queryAll('itemGroup').forEach(itemGroupEl => {
            itemGroupEl.hidden = !this.hydrator
                .queryAll('item', itemGroupEl)
                .some(itemEl => !itemEl.hidden);
        });

        const hasVisibleItems = this.hydrator.queryAll('item').some(itemEl => !itemEl.hidden);
        contentEl?.toggleAttribute('data-empty', !hasVisibleItems);
        listEl?.toggleAttribute('data-empty', !hasVisibleItems);

        const emptyEl = this.hydrator.query('empty');
        if (emptyEl) {
            this.spreadProps(emptyEl, this.api.getEmptyProps());
            emptyEl.hidden = hasVisibleItems;
        }

        const clearTriggerEl = this.hydrator.query('clearTrigger');
        if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
    };
}
