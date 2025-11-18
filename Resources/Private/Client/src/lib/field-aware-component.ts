import type { Service } from '@zag-js/core';
import { Component } from '.';
import {
	getFieldMachineFor,
	type FieldMachine,
} from '../../../Primitives/Field/src/field.registry';

const fieldProps = ['invalid', 'disabled', 'readOnly', 'required'] as const;
type FieldProps = (typeof fieldProps)[number];

const fieldAccessors: Record<FieldProps, (s: Service<any>) => boolean> = {
	disabled: s => s.context.get('disabled'),
	readOnly: s => s.context.get('readOnly'),
	required: s => s.context.get('required'),
	invalid: s => s.context.get('invalid'),
	// invalid: s => s.computed('invalid'),
};

export abstract class FieldAwareComponent<Props, Api> extends Component<Props, Api> {
	protected subscribedToField = false;
	protected fieldMachine: FieldMachine | undefined;
	protected closestField: Element | null = null;

	// protected abstract fieldLinkConfig: FieldLinkedConfig<Props>;
	// return the props with ids or so from the field like
	// {
	//     ids: {
	//         label: getFieldLabelId(fieldMachine.scope),
	//         hiddenInput: getFieldControlId(fieldMachine.scope),
	//         ...props.ids,
	//     },
	//     ...props,
	// }
	protected abstract propsWithField(props: Partial<Props>, fieldMachine: FieldMachine): Props;

	protected getClosestField() {
		return (
			this.closestField ||
			this.getElement('root')?.closest('[data-scope="field"][data-part="root"]') ||
			null
		);
	}

	protected withFieldProps(props: Props): Props {
		this.closestField = this.getClosestField();

		if (!this.closestField) return props;

		this.fieldMachine = getFieldMachineFor(this.closestField as HTMLElement);
		if (this.fieldMachine) {
			return this.propsWithField(props, this.fieldMachine);
		} else {
			const handler = () => {
				this.fieldMachine = getFieldMachineFor(this.closestField as HTMLElement);
				this.updateProps(this.propsWithField(this.userProps!, this.fieldMachine!));
				this.closestField?.removeEventListener(
					'fluid-primitives:field:registered',
					handler
				);
			};
			this.closestField.addEventListener('fluid-primitives:field:registered', handler);
		}

		return props;
	}

	subscribeToFieldService() {
		if (this.subscribedToField) return;

		// TODO: why is it only working when we query the closest field again here?
		this.closestField = this.getClosestField();

		if (!this.closestField) return;

		if (this.fieldMachine) {
			this.fieldMachine.subscribe(snapshot => {
				queueMicrotask(() => {
					let propsToUpdate: Partial<Record<FieldProps, boolean>> = {};

					for (const prop of fieldProps) {
						const newValue = !!fieldAccessors[prop](snapshot);
						// const currentValue = !!this.api[prop as keyof Api];
						const currentValue = !!this.machine.prop(prop);

						if (newValue !== currentValue) {
							propsToUpdate[prop] = newValue;
						}
					}
					// console.log(this.getName(), { propsToUpdate });

					if (Object.keys(propsToUpdate).length > 0) {
						this.updateProps(propsToUpdate as Partial<Props>);
					} else {
						this.api = this.initApi();
						this.render();
					}
				});
			});
			this.subscribedToField = true;
		} else {
			const handler = () => {
				this.subscribeToFieldService();
				// this.render();
				this.closestField!.removeEventListener(
					'fluid-primitives:field:registered',
					handler
				);
			};
			this.closestField!.addEventListener('fluid-primitives:field:registered', handler);
		}
	}
}
