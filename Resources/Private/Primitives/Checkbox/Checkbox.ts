import * as checkbox from '@zag-js/checkbox';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

export class Checkbox extends FieldAwareComponent<checkbox.Props, checkbox.Api> {
	static name = 'checkbox';

	propsWithField(props: checkbox.Props, fieldMachine: FieldMachine): checkbox.Props {
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
				hiddenInput: fieldDom.getControlId(fieldMachine.scope),
			},
		};
	}

	initMachine(props: checkbox.Props): Machine<any> {
		props = this.withFieldProps(props);
		return new Machine(checkbox.machine, props);
	}

	initApi() {
		return checkbox.connect(this.machine.service, normalizeProps);
	}

	render() {
		this.subscribeToFieldService();

		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

		const hiddenInputEl = this.getElement('hidden-input');
		if (hiddenInputEl) {
			const mergedProps = mergeProps(this.api.getHiddenInputProps(), {
				'aria-describedby': this.fieldMachine?.ctx.get('describeIds') || undefined,
			});
			this.spreadProps(hiddenInputEl, mergedProps);
		}
	}
}
