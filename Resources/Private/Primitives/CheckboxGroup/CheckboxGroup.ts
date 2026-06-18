import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Form/src/form.registry';
import { connect } from './src/checkbox-group.connect';
import { machine } from './src/checkbox-group.machine';
import { registerCheckboxGroup, unregisterCheckboxGroup } from './src/checkbox-group.registry';
import type { CheckboxGroupApi, CheckboxGroupProps } from './src/checkbox-group.types';

export class CheckboxGroup extends FieldAwareComponent<CheckboxGroupProps, CheckboxGroupApi> {
	static name = 'checkbox-group';

	propsWithField(props: CheckboxGroupProps, fieldMachine: FieldMachine): CheckboxGroupProps {
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
			},
		};
	}

	initMachine(props: CheckboxGroupProps) {
		props = this.withFieldProps(props);
		const createdMachine = new Machine(machine, props);
		registerCheckboxGroup(this.getElement('root'), createdMachine);
		return createdMachine;
	}

	initApi() {
		return connect(this.machine.service, normalizeProps);
	}

	render() {
		this.subscribeToFieldService();

		const rootEl = this.getElement('root');
		if (rootEl) {
			const mergedProps = mergeProps(this.api.getRootProps(), {
				'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
			});
			this.spreadProps(rootEl, mergedProps);
		}

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());
	}

	destroy() {
		unregisterCheckboxGroup(this.getElement('root'));
		super.destroy();
	}
}
