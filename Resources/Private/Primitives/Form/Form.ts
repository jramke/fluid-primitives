import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/form.connect';
import { machine } from './src/form.machine';
import { registerFormMachine } from './src/form.registry';
import type { FormApi, FormProps } from './src/form.types';

export class Form extends Component<FormProps, FormApi> {
	static name = 'form';
	// private mounted = false;

	initMachine(props: FormProps) {
		const createdMachine = new Machine(machine, props);
		registerFormMachine(this.getElement('form'), createdMachine);
		return createdMachine;
	}

	initApi() {
		return connect(this.machine.service, normalizeProps);
	}

	render() {
		console.log('form', {
			errors: this.api.getErrors(),
			values: this.api.getValues(),
			dirty: this.api.getDirty(),
		});

		const formEl = this.getElement('form') as HTMLFormElement | null;
		if (!formEl) return;

		// if (!this.mounted) {
		// 	this.mounted = true;
		// 	registerFormService(formEl, this.machine);
		// }

		this.spreadProps(formEl, this.api.getFormProps());

		// const cleanup = () => {
		// 	unregisterFormService(formEl);
		// };
		// (formEl as any).__fl_cleanup__ ??= cleanup;
	}
}
