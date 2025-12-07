import * as select from '@zag-js/select';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

export class Select extends FieldAwareComponent<select.Props, select.Api> {
	static name = 'select';

	propsWithField(props: select.Props, fieldMachine: FieldMachine): select.Props {
		return {
			...props,
			disabled: props.disabled ?? fieldMachine.ctx.get('disabled'),
			readOnly: props.readOnly ?? fieldMachine.ctx.get('readOnly'),
			required: props.required ?? fieldMachine.ctx.get('required'),
			invalid: props.invalid ?? fieldMachine.ctx.get('invalid'),
			name: props.name ?? fieldMachine.prop('name'),
			ids: {
				...props.ids,
				label: fieldDom.getLabelId(fieldMachine.scope),
				hiddenSelect: fieldDom.getControlId(fieldMachine.scope),
			},
		};
	}

	transformProps(props: select.Props) {
		return {
			...props,
			get collection() {
				return getListCollectionFromHydrationData(props.collection);
			},
		};
	}

	initMachine(props: select.Props): Machine<any> {
		props = this.withFieldProps(props);
		return new Machine(select.machine, this.transformProps(props));
	}

	initApi() {
		return select.connect(this.machine.service, normalizeProps);
	}

	render = () => {
		this.subscribeToFieldService();

		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

		const hiddenSelectEl = this.getElement('hidden-select');
		if (hiddenSelectEl) {
			const mergedProps = mergeProps(this.api.getHiddenSelectProps(), {
				'aria-describedby': this.fieldMachine?.ctx.get('describeIds') || undefined,
			});
			this.spreadProps(hiddenSelectEl, mergedProps);
		}

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const triggerEl = this.getElement('trigger');
		if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

		const positionerEl = this.getElement('positioner');
		if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

		const contentEl = this.getElement('content');
		if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

		const valueTextEl = this.getElement('value-text');
		if (valueTextEl)
			this.spreadProps(
				valueTextEl,
				mergeProps(this.api.getValueTextProps(), {
					children: this.api.valueAsString || valueTextEl.dataset.placeholder,
				})
			);

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
		itemEls.forEach(itemEl => {
			const item = this.api.collection.find(itemEl.dataset.value);
			if (item) {
				this.spreadProps(itemEl, this.api.getItemProps({ item }));
			}
		});

		const itemTextEls = this.getElements('item-text');
		itemTextEls.forEach(itemTextEl => {
			const item = this.api.collection.find(itemTextEl.dataset.value);
			if (item) {
				this.spreadProps(itemTextEl, this.api.getItemTextProps({ item }));
			}
		});

		const itemIndicatorEls = this.getElements('item-indicator');
		itemIndicatorEls.forEach(itemIndicatorEl => {
			const item = this.api.collection.find(itemIndicatorEl.dataset.value);
			if (item) {
				this.spreadProps(itemIndicatorEl, this.api.getItemIndicatorProps({ item }));
			}
		});

		const clearTriggerEl = this.getElement('clear-trigger');
		if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
	};
}
