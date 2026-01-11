import * as numberInput from '@zag-js/number-input';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

export class NumberInput extends FieldAwareComponent<numberInput.Props, numberInput.Api> {
	static name = 'number-input';

	propsWithField(props: numberInput.Props, fieldMachine: FieldMachine): numberInput.Props {
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
				input: fieldDom.getControlId(fieldMachine.scope),
			},
		};
	}

	initMachine(props: numberInput.Props): Machine<any> {
		props = this.withFieldProps(props);
		return new Machine(numberInput.machine, props);
	}

	initApi() {
		return numberInput.connect(this.machine.service, normalizeProps);
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
				'aria-describedby': this.fieldMachine?.ctx.get('describeIds') || undefined,
			});
			this.spreadProps(inputEl, mergedProps);
		}

		const incrementTriggerEl = this.getElement('increment-trigger');
		if (incrementTriggerEl)
			this.spreadProps(incrementTriggerEl, this.api.getIncrementTriggerProps());

		const decrementTriggerEl = this.getElement('decrement-trigger');
		if (decrementTriggerEl)
			this.spreadProps(decrementTriggerEl, this.api.getDecrementTriggerProps());

		const valueTextEl = this.getElement('value-text');
		if (valueTextEl) this.spreadProps(valueTextEl, this.api.getValueTextProps());

		const scrubberEl = this.getElement('scrubber');
		if (scrubberEl) this.spreadProps(scrubberEl, this.api.getScrubberProps());
	}
}
