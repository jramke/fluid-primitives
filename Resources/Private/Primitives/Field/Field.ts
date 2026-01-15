import { Component, Machine, normalizeProps } from '../../Client';
import { registerFieldMachineForForm } from '../Form/src/form.registry';
import { connect } from './src/field.connect';
import { machine } from './src/field.machine';
import { registerFieldMachine } from './src/field.registry';
import type { FieldApi, FieldProps } from './src/field.types';

export class Field extends Component<FieldProps, FieldApi> {
	static name = 'field';

	private subscribedToForm = false;

	initMachine(props: FieldProps) {
		const createdMachine = new Machine(machine, props);
		registerFieldMachine(this.getElement('root'), createdMachine);
		registerFieldMachineForForm(this.getElement('root'), createdMachine);
		return createdMachine;
	}

	initApi() {
		return connect(this.machine.service, normalizeProps);
	}

	subscribeToFormMachine() {
		if (this.subscribedToForm) return;

		const formMachine = this.api.getFormMachine();
		if (!formMachine) return;

		this.subscribedToForm = true;
		formMachine.subscribe(() => {
			this.machine.notify();
		});
	}

	render() {
		this.subscribeToFormMachine();

		const rootEl = this.getElement('root');
		if (rootEl) {
			this.spreadProps(rootEl, this.api.getRootProps());
		}

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

		const descriptionEl = this.getElement('description');
		if (descriptionEl) this.spreadProps(descriptionEl, this.api.getDescriptionProps());

		const errorEl = this.getElement('error');
		if (errorEl) {
			this.spreadProps(errorEl, this.api.getErrorProps());
			const msg = this.api.getErrorText();
			if (typeof (errorEl as any).textContent !== 'undefined') {
				(errorEl as HTMLElement).textContent = msg ?? '';
			}
		}
	}
}
