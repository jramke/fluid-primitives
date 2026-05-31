import * as checkbox from '@zag-js/checkbox';
import { FieldAwareComponent, Machine, mergeProps, normalizeProps } from '../../Client';
import { connect as connectCheckboxGroup } from '../CheckboxGroup/src/checkbox-group.connect';
import {
	getCheckboxGroupMachineFor,
	type CheckboxGroupMachine,
} from '../CheckboxGroup/src/checkbox-group.registry';
import type { CheckboxGroupApi } from '../CheckboxGroup/src/checkbox-group.types';
import * as fieldDom from '../Field/src/field.dom';
import type { FieldMachine } from '../Field/src/field.registry';

export class Checkbox extends FieldAwareComponent<checkbox.Props, checkbox.Api> {
	static name = 'checkbox';

	private checkboxGroupMachine: CheckboxGroupMachine | undefined;
	private checkboxGroupApi: CheckboxGroupApi | undefined;
	private groupUnsubscribe: (() => void) | undefined;
	private subscribedToGroup = false;
	private closestCheckboxGroup: HTMLElement | null = null;
	private syncingFromGroup = false; // Prevents callback loop when syncing from group

	propsWithField(props: checkbox.Props, fieldMachine: FieldMachine): checkbox.Props {
		const isInGroup = !!this.getClosestCheckboxGroup();

		return {
			...props,
			disabled: props.disabled ?? fieldMachine.context.get('disabled'),
			readOnly: props.readOnly ?? fieldMachine.context.get('readOnly'),
			required: props.required ?? fieldMachine.context.get('required'),
			invalid: props.invalid ?? fieldMachine.context.get('invalid'),
			name: props.name ?? fieldMachine.prop('name'),
			ids: {
				...props.ids,
				label: isInGroup ? undefined : fieldDom.getLabelId(fieldMachine.scope),
				hiddenInput: isInGroup ? undefined : fieldDom.getControlId(fieldMachine.scope),
			},
		};
	}

	private getClosestCheckboxGroup(): HTMLElement | null {
		return (
			this.closestCheckboxGroup ||
			(this.getElement('root')?.closest(
				'[data-scope="checkbox-group"][data-part="root"]'
			) as HTMLElement | null)
		);
	}

	/**
	 * Build props merged with CheckboxGroup context.
	 * Called by withGroupProps and when group registers late.
	 */
	private buildGroupProps(props: checkbox.Props): checkbox.Props {
		if (!this.checkboxGroupMachine || !props.value) {
			return props;
		}

		this.checkboxGroupApi = connectCheckboxGroup(
			this.checkboxGroupMachine.service,
			normalizeProps
		);

		const groupItemProps = this.checkboxGroupApi.getItemProps({ value: props.value });

		return {
			...props,
			// Use defaultChecked (not checked) so the checkbox manages its own state.
			// Setting "checked" makes it controlled, which breaks toggling.
			defaultChecked: groupItemProps.checked,
			disabled: props.disabled ?? groupItemProps.disabled,
			readOnly: props.readOnly ?? groupItemProps.readOnly,
			invalid: props.invalid ?? groupItemProps.invalid,
			name: props.name ?? groupItemProps.name,
			onCheckedChange: details => {
				// Skip group toggle if this change originated from group sync (prevents loop)
				if (this.syncingFromGroup) {
					props.onCheckedChange?.(details);
					return;
				}
				// Call group's toggle
				groupItemProps.onCheckedChange();
				// Also call any user-provided callback
				props.onCheckedChange?.(details);
			},
		};
	}

	/**
	 * Merge props from CheckboxGroup if this checkbox is inside one.
	 * Similar to withFieldProps - handles late registration via event listener.
	 */
	private withGroupProps(props: checkbox.Props): checkbox.Props {
		this.closestCheckboxGroup = this.getClosestCheckboxGroup();

		if (!this.closestCheckboxGroup || !props.value) {
			return props;
		}

		this.checkboxGroupMachine = getCheckboxGroupMachineFor(this.closestCheckboxGroup);

		if (this.checkboxGroupMachine) {
			return this.buildGroupProps(props);
		} else {
			// Group not registered yet - listen for registration event
			const handler = () => {
				this.checkboxGroupMachine = getCheckboxGroupMachineFor(
					this.getClosestCheckboxGroup()
				);
				if (this.checkboxGroupMachine) {
					this.updateProps(this.buildGroupProps(this.userProps as checkbox.Props));
				}
				this.closestCheckboxGroup?.removeEventListener(
					'fluid-primitives:checkbox-group:registered',
					handler
				);
			};
			this.closestCheckboxGroup.addEventListener(
				'fluid-primitives:checkbox-group:registered',
				handler
			);
		}

		return props;
	}

	initMachine(props: checkbox.Props): Machine<any> {
		props = this.withFieldProps(props);
		props = this.withGroupProps(props);
		return new Machine(checkbox.machine, props);
	}

	initApi() {
		return checkbox.connect(this.machine.service, normalizeProps);
	}

	getFieldValue() {
		// When inside a CheckboxGroup, the group reports the value array instead.
		if (this.getClosestCheckboxGroup()) return undefined;
		// Boolean carries best in JS-land; the FormData serializer maps `true` to
		// the input's `value` attribute (default '1') and omits `false`.
		return this.api.checked === true;
	}

	render() {
		this.subscribeToFieldService();
		this.subscribeToCheckboxGroup();
		this.syncValueToField();

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
				'aria-describedby': this.fieldMachine?.context.get('describeIds') || undefined,
				...(!!this.fieldMachine
					? {
							onBlur: (e: FocusEvent) => {
								// TODO: dont trigger blur when focusing another checkbox in the same group
								// TODO: implement other primitives on blurs
								console.log(e);
								this.blurFieldValue();
							},
						}
					: {}), // trigger on blur when in form/field
			});
			this.spreadProps(hiddenInputEl, mergedProps);
		}
	}

	/**
	 * Subscribe to CheckboxGroup state changes to sync checked/disabled state.
	 * Similar to subscribeToFieldService.
	 */
	private subscribeToCheckboxGroup() {
		if (this.subscribedToGroup) return;

		this.closestCheckboxGroup = this.getClosestCheckboxGroup();
		if (!this.closestCheckboxGroup) return;

		if (!this.checkboxGroupMachine) {
			this.checkboxGroupMachine = getCheckboxGroupMachineFor(this.closestCheckboxGroup);
		}

		if (this.checkboxGroupMachine && this.userProps?.value) {
			const value = this.userProps.value;

			this.groupUnsubscribe = this.checkboxGroupMachine.subscribe(() => {
				queueMicrotask(() => {
					this.checkboxGroupApi = connectCheckboxGroup(
						this.checkboxGroupMachine!.service,
						normalizeProps
					);

					const groupItemProps = this.checkboxGroupApi.getItemProps({ value });
					const currentChecked = this.api.checked;

					// Sync checked state if it differs (for programmatic changes like addValue/setValue)
					if (groupItemProps.checked !== currentChecked) {
						// Set flag to prevent onCheckedChange from toggling group again.
						// Use queueMicrotask to reset since dispatchChangeEvent is async.
						this.syncingFromGroup = true;
						this.api.setChecked(groupItemProps.checked);
						queueMicrotask(() => {
							this.syncingFromGroup = false;
						});
					}

					// Always update disabled/invalid props
					this.machine.updateProps({
						disabled: this.userProps?.disabled || groupItemProps.disabled,
						invalid: groupItemProps.invalid,
					});
				});
			});
			this.subscribedToGroup = true;
		} else if (this.closestCheckboxGroup) {
			// Group not registered yet - wait for it
			const handler = () => {
				this.subscribeToCheckboxGroup();
				this.closestCheckboxGroup?.removeEventListener(
					'fluid-primitives:checkbox-group:registered',
					handler
				);
			};
			this.closestCheckboxGroup.addEventListener(
				'fluid-primitives:checkbox-group:registered',
				handler
			);
		}
	}

	destroy() {
		this.groupUnsubscribe?.();
		super.destroy();
	}
}
