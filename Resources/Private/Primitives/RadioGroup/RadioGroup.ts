import * as radioGroup from '@zag-js/radio-group';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

export class RadioGroup extends FieldAwareComponent<radioGroup.Props, radioGroup.Api> {
	static name = 'radio-group';

	propsWithField(props: radioGroup.Props, fieldMachine: FieldMachine): radioGroup.Props {
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
				itemHiddenInput: fieldDom.getControlId(fieldMachine.scope),
			},
		};
	}

	initMachine(props: radioGroup.Props): Machine<any> {
		props = this.withFieldProps(props);
		return new Machine(radioGroup.machine, props);
	}

	initApi() {
		return radioGroup.connect(this.machine.service, normalizeProps);
	}

	render() {
		this.subscribeToFieldService();

		const rootEl = this.getElement('root');
		if (rootEl)
			this.spreadProps(
				rootEl,
				mergeProps(this.api.getRootProps(), {
					'aria-describedby': this.fieldMachine?.ctx.get('describeIds') || undefined,
				})
			);

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const itemEls = this.getElements('item');
		itemEls.forEach(itemEl => {
			this.spreadProps(
				itemEl,
				this.api.getItemProps({
					value: itemEl.dataset.value!,
					disabled: itemEl.getAttribute('data-disabled') === 'true',
					invalid: itemEl.getAttribute('data-invalid') === 'true',
				})
			);
		});

		const itemTextEls = this.getElements('item-text');
		itemTextEls.forEach(itemTextEl => {
			this.spreadProps(
				itemTextEl,
				this.api.getItemTextProps({
					value: itemTextEl.dataset.value!,
					disabled: itemTextEl.getAttribute('data-disabled') === 'true',
					invalid: itemTextEl.getAttribute('data-invalid') === 'true',
				})
			);
		});

		const itemControlEls = this.getElements('item-control');
		itemControlEls.forEach(itemControlEl => {
			this.spreadProps(
				itemControlEl,
				this.api.getItemControlProps({
					value: itemControlEl.dataset.value!,
					disabled: itemControlEl.getAttribute('data-disabled') === 'true',
					invalid: itemControlEl.getAttribute('data-invalid') === 'true',
				})
			);
		});

		const itemHiddenInputEls = this.getElements('item-hidden-input');
		itemHiddenInputEls.forEach(itemHiddenInputEl => {
			this.spreadProps(
				itemHiddenInputEl,
				this.api.getItemHiddenInputProps({
					value: itemHiddenInputEl.dataset.value!,
					disabled: itemHiddenInputEl.getAttribute('data-disabled') === 'true',
					invalid: itemHiddenInputEl.getAttribute('data-invalid') === 'true',
				})
			);
		});

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
	}
}
