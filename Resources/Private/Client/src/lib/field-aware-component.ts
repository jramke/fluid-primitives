import type { Service } from '@zag-js/core';
import { Component } from '.';
import { blurField, pushFieldValue } from '../../../Primitives/Field/src/field.machine';
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
};

export abstract class FieldAwareComponent<Props, Api> extends Component<Props, Api> {
	protected subscribedToField = false;
	protected fieldMachine: FieldMachine | undefined;
	protected closestField: HTMLElement | null = null;

	protected abstract propsWithField(props: Partial<Props>, fieldMachine: FieldMachine): Props;

	/**
	 * Return the primitive's current value so it can be pushed into the bound
	 * TanStack field. Override in primitives that participate in form validation
	 * (NumberInput, Select, RadioGroup, Checkbox, ...). Returning `undefined`
	 * (the default) opts out of value syncing.
	 */
	protected getFieldValue(): unknown {
		return undefined;
	}

	private seededFieldValue = false;

	/**
	 * Pushes the primitive's current value into the bound TanStack field. The
	 * first push (initial render) only seeds the value; subsequent pushes from
	 * user interaction mark the field touched and run change validators.
	 */
	protected syncValueToField() {
		if (!this.fieldMachine) return;
		const value = this.getFieldValue();
		if (value === undefined) return;

		const wrote = pushFieldValue(this.fieldMachine, value, this.seededFieldValue);
		if (wrote || this.fieldMachine.refs?.get('field')) {
			this.seededFieldValue = true;
		}
	}

	/** Marks the bound TanStack field as blurred. */
	protected blurFieldValue() {
		if (!this.fieldMachine) return;
		blurField(this.fieldMachine);
	}

	protected getClosestField() {
		return (
			this.closestField ||
			(this.getElement('root')?.closest(
				'[data-scope="field"][data-part="root"]'
			) as HTMLElement) ||
			null
		);
	}

	protected withFieldProps(props: Props): Props {
		this.closestField = this.getClosestField();

		if (!this.closestField) return props;

		this.fieldMachine = getFieldMachineFor(this.closestField);
		if (this.fieldMachine) {
			return this.propsWithField(props, this.fieldMachine);
		} else {
			const handler = () => {
				this.fieldMachine = getFieldMachineFor(this.closestField);
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

		this.closestField = this.getClosestField();
		if (!this.closestField) return;

		if (!this.fieldMachine) {
			this.fieldMachine = getFieldMachineFor(this.closestField);
		}

		if (this.fieldMachine) {
			this.fieldMachine.subscribe(snapshot => {
				queueMicrotask(() => {
					let propsToUpdate: Partial<Record<FieldProps, boolean>> = {};

					for (const prop of fieldProps) {
						const newValue = !!fieldAccessors[prop](snapshot);
						const currentValue = !!this.machine.prop(prop);

						if (newValue !== currentValue) {
							propsToUpdate[prop] = newValue;
						}
					}

					if (Object.keys(propsToUpdate).length > 0) {
						this.updateProps(propsToUpdate as Partial<Props>);
					} else {
						// notify is marked as private but that does not prevent runtime access
						// @ts-expect-error
						this.machine.notify();
					}
				});
			});
			this.subscribedToField = true;
		} else {
			const handler = () => {
				this.subscribeToFieldService();
				this.closestField!.removeEventListener(
					'fluid-primitives:field:registered',
					handler
				);
			};
			this.closestField!.addEventListener('fluid-primitives:field:registered', handler);
		}
	}
}
