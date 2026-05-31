import * as zagSwitch from '@zag-js/switch';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

export class Switch extends FieldAwareComponent<zagSwitch.Props, zagSwitch.Api> {
	static name = 'switch';

	propsWithField(props: zagSwitch.Props, fieldMachine: FieldMachine): zagSwitch.Props {
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
				hiddenInput: fieldDom.getControlId(fieldMachine.scope),
			},
		};
	}

	initMachine(props: zagSwitch.Props): Machine<any> {
		props = this.withFieldProps(props);
		return new Machine(zagSwitch.machine, props);
	}

	initApi() {
		return zagSwitch.connect(this.machine.service, normalizeProps);
	}

	getFieldValue() {
		// JS-typed boolean. The FormData serializer maps `true` -> '1' on submit.
		return this.api.checked === true;
	}

	render() {
		this.subscribeToFieldService();
		this.syncValueToField();

		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

		const thumbEl = this.getElement('thumb');
		if (thumbEl) this.spreadProps(thumbEl, this.api.getThumbProps());

		const hiddenInputEl = this.getElement('hidden-input');
		if (hiddenInputEl) {
			const mergedProps = mergeProps(this.api.getHiddenInputProps(), {
				'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
			});
			this.spreadProps(hiddenInputEl, mergedProps);
		}

		const checkedIndicatorEl = this.getElement('indicator-checked');
		if (checkedIndicatorEl) {
			this.spreadProps(
				checkedIndicatorEl,
				normalizeProps.element({
					'aria-hidden': true,
					hidden: this.api.checked ? undefined : true,
					'data-state': 'checked',
				})
			);
		}

		const uncheckedIndicatorEl = this.getElement('indicator-unchecked');
		if (uncheckedIndicatorEl) {
			this.spreadProps(
				uncheckedIndicatorEl,
				normalizeProps.element({
					'aria-hidden': true,
					hidden: this.api.checked ? true : undefined,
					'data-state': 'unchecked',
				})
			);
		}
	}
}
