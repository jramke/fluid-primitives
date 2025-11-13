import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/field.connect';
import { machine } from './src/field.machine';
import { registerFieldMachine } from './src/field.registry';
import type { FieldApi, FieldProps } from './src/field.types';

export class Field extends Component<FieldProps, FieldApi> {
	static name = 'field';

	// private mounted = false;
	private subscribedToForm = false;

	initMachine(props: FieldProps) {
		const createdMachine = new Machine(machine, props);
		registerFieldMachine(this.getElement('root'), createdMachine);
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
			// const { state } = test;
			// console.log('test sub', this.api.name, test, state.get());
			// console.log('form snap from field', this.api.name, context.get('errors'));
			this.render();
		});
	}

	render() {
		this.subscribeToFormMachine();

		const rootEl = this.getElement('root');
		if (rootEl) {
			this.spreadProps(rootEl, this.api.getRootProps());
		}
		// if (!this.mounted) {
		// 	this.mounted = true;
		// 	registerFieldService(rootEl, this.machine);
		// }
		console.log('render field', this.api.name, {
			invalid: this.api.invalid,
			errors: this.api.errors,
		});

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

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
