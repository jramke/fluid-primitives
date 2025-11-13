import * as checkbox from '@zag-js/checkbox';
import { Component, Machine, mergeProps, normalizeProps } from '../../Client';
import {
	getControlId as getFieldControlId,
	getLabelId as getFieldLabelId,
} from '../Field/src/field.dom';
import { getFieldMachineFor, type FieldMachine } from '../Field/src/field.registry';

export class Checkbox extends Component<checkbox.Props, checkbox.Api> {
	static name = 'checkbox';

	private subscribedToField = false;
	private fieldMachine: FieldMachine | undefined = undefined;
	private closestField: Element | null = null;

	getClosestField() {
		return (
			this.closestField ||
			this.getElement('root')?.closest('[data-scope="field"][data-part="root"]') ||
			null
		);
	}

	initMachine(props: checkbox.Props): Machine<any> {
		this.closestField = this.getClosestField();
		console.log({ checkboxField: this.closestField });

		if (this.closestField) {
			this.fieldMachine = getFieldMachineFor(this.closestField as HTMLElement);
			if (this.fieldMachine) {
				// console.log('ids', this.fieldMachine.scope.ids, this.fieldMachine.scope.id);
				props = {
					ids: {
						label: getFieldLabelId(this.fieldMachine.scope),
						hiddenInput: getFieldControlId(this.fieldMachine.scope),
						...props.ids,
					},
					...props,
				};
			} else {
				const handler = () => {
					this.fieldMachine = getFieldMachineFor(this.closestField as HTMLElement);
					this.updateProps({
						ids: {
							label: getFieldLabelId(this.fieldMachine!.scope),
							hiddenInput: getFieldControlId(this.fieldMachine!.scope),
							...this.userProps?.ids,
						},
					});
					this.closestField!.removeEventListener(
						'fluid-primitives:field:registered',
						handler
					);
				};
				this.closestField.addEventListener('fluid-primitives:field:registered', handler);
			}
		}

		return new Machine(checkbox.machine, props);
	}

	initApi() {
		return checkbox.connect(this.machine.service, normalizeProps);
	}

	subscribeToFieldService() {
		if (this.subscribedToField) return;

		this.closestField = this.getClosestField();
		// TODO: why is it only working when we query the closest field again here?
		console.log('subscibung checkbox field', this.fieldMachine, this.closestField);

		if (this.fieldMachine) {
			this.fieldMachine.subscribe(snapshot => {
				console.log('checkbox field', snapshot.context.get('invalid'));
				const fieldProps = ['invalid', 'disabled', 'readOnly', 'required'] as const;
				const fieldValues = fieldProps.map(prop => !!snapshot.context.get(prop));
				const currentValues = fieldProps.map(prop => !!this.machine.prop(prop));
				let propsToUpdate: Partial<checkbox.Props> = {};
				fieldProps.forEach((prop, index) => {
					if (fieldValues[index] !== currentValues[index]) {
						propsToUpdate[prop] = fieldValues[index];
					}
				});
				if (Object.keys(propsToUpdate).length > 0) {
					this.updateProps(propsToUpdate);
				} else {
					this.render();
				}
			});
			this.subscribedToField = true;
		} else {
			const handler = () => {
				this.subscribeToFieldService();
				this.render();
				this.closestField!.removeEventListener(
					'fluid-primitives:field:registered',
					handler
				);
			};
			this.closestField!.addEventListener('fluid-primitives:field:registered', handler);
		}
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
			// TODO: mergeprops is not working as expected here with the style attributes
			const mergedProps = mergeProps(this.api.getHiddenInputProps(), {
				'aria-describedby': this.fieldMachine?.ctx.get('describeIds') || undefined,
			});
			this.spreadProps(hiddenInputEl, mergedProps);
		}
	}
}
