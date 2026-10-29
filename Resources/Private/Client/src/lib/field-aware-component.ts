import { Component, normalizeProps } from '.';
import {
    connectField,
    createFieldHandleExtras,
    type FieldClientApi,
} from '../../../Primitives/Field/src/field.handle';
import {
    getFieldMachineFor,
    type FieldMachine,
} from '../../../Primitives/Field/src/field.registry';
import type { FieldConstraint } from '../../../Primitives/Field/src/field.types';
import { trimArraySuffix } from '../../../Primitives/Form/src/form.path';

const booleanFieldProps = ['invalid', 'disabled', 'readOnly', 'required'] as const;
// `name` is tracked reactively too (not just merged once at mount) so that
// renaming a field (e.g. Form.api.renameField(), used to keep recurring
// field rows contiguously indexed) also updates Field-aware primitives like
// NumberInput/Select/Checkbox/RadioGroup that wrap a field, not just plain
// asChild-wrapped native inputs (whose `name` attribute is already kept in
// sync directly by Field's own control props).
const fieldProps = [...booleanFieldProps, 'name'] as const;
type FieldProp = (typeof fieldProps)[number];

/**
 * Base class of a primitive that can sit inside a Field. It takes `name`, `disabled`, `readOnly`,
 * `required` and `invalid` from the closest Field and follows its changes, and it tells the field
 * once it has hydrated.
 */
export abstract class FieldAwareComponent<Props, Api> extends Component<Props, Api> {
    protected subscribedToField = false;
    protected fieldMachine: FieldMachine | undefined;
    protected closestField: HTMLElement | null = null;

    protected abstract propsWithField(props: Partial<Props>, field: FieldClientApi): Props;

    /**
     * A rule only this primitive knows, such as a soft `maxLength`, that the closest field runs on
     * every validation. A violation makes the field invalid like a native constraint does. A primitive
     * overrides this to register one, there is none by default.
     */
    protected getFieldConstraint(): FieldConstraint | undefined {
        return undefined;
    }

    /**
     * Disables the empty-value input (`<ui:emptyValueInput />`) while the control has a value or is
     * disabled, so the form data holds the value or the empty one, never both, and keeps its name,
     * `data-empty-value` and form on those of the control, which a renamed field or an `updateProps()`
     * changes. Call it from `render()`.
     *
     * @param hasValue - Whether the control holds a value right now.
     * @param disabled - Whether the control is disabled, which disables the input as well.
     */
    protected syncEmptyValueInput(hasValue: boolean, disabled?: boolean) {
        const emptyValueInputEl = this.hydrator.query('emptyValueInput');
        if (emptyValueInputEl) {
            const name: string | undefined = this.machine.prop('name');
            const form: string | undefined = this.machine.prop('form');
            this.spreadProps(
                emptyValueInputEl,
                normalizeProps.input({
                    name: name && trimArraySuffix(name),
                    'data-empty-value': name,
                    form,
                    disabled: hasValue || !!disabled,
                })
            );
        }
    }

    /** The field's current api, for what a primitive merges into its own props when it renders. */
    protected get field(): FieldClientApi | undefined {
        return this.fieldMachine ? connectField(this.fieldMachine) : undefined;
    }

    protected getClosestField(): HTMLElement | null {
        return (
            this.closestField ||
            (this.hydrator.query('root')?.closest('[data-field-root]') as HTMLElement) ||
            null
        );
    }

    /** Builds the component like `Component.init()`, then tells the field that it is hydrated. */
    init() {
        super.init();
        this.notifyFieldHydrated();
        return this;
    }

    protected withFieldProps(props: Props): Props {
        this.closestField = this.getClosestField();

        if (!this.closestField) return props;

        this.fieldMachine = getFieldMachineFor(this.closestField);
        if (this.fieldMachine) {
            return this.propsWithField(props, connectField(this.fieldMachine));
        } else {
            const handler = () => {
                this.fieldMachine = getFieldMachineFor(this.closestField);
                this.updateProps(
                    this.propsWithField(this.userProps, connectField(this.fieldMachine!))
                );
                this.notifyFieldHydrated();
                this.closestField?.removeEventListener(
                    'fluid-primitives:field:registered',
                    handler
                );
            };
            this.closestField.addEventListener('fluid-primitives:field:registered', handler);
        }

        return props;
    }

    /**
     * Applies every later change of the field to the machine props. It waits for the field if that
     * has not registered yet, and does nothing when there is no field around.
     */
    subscribeToFieldService() {
        if (this.subscribedToField) return;

        this.closestField = this.getClosestField();
        if (!this.closestField) return;

        if (!this.fieldMachine) {
            this.fieldMachine = getFieldMachineFor(this.closestField);
        }

        if (this.fieldMachine) {
            const fieldMachine = this.fieldMachine;
            fieldMachine.subscribe(() => {
                queueMicrotask(() => {
                    const field = connectField(fieldMachine);
                    const propsToUpdate: Partial<Record<FieldProp, boolean | string>> = {};

                    for (const prop of fieldProps) {
                        const newValue = prop === 'name' ? field.name : !!field[prop];
                        const currentValue =
                            prop === 'name' ? this.machine.prop(prop) : !!this.machine.prop(prop);

                        if (newValue !== currentValue) {
                            propsToUpdate[prop] = newValue;
                        }
                    }

                    if (Object.keys(propsToUpdate).length > 0) {
                        this.updateProps(propsToUpdate as Partial<Props>);
                    } else {
                        // the field's aria-describedby (its visible error texts) may have changed
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

    /** Stops the machine and releases the hydrator, and takes its constraint back from the field. */
    destroy() {
        if (this.fieldMachine && this.getFieldConstraint()) {
            createFieldHandleExtras(this.fieldMachine).setConstraint(null);
        }
        super.destroy();
    }

    /**
     * The field measured its starting value from the server HTML; a primitive may hydrate to a
     * different state (a defaultChecked checkbox, a Select's selected option).
     */
    private notifyFieldHydrated() {
        this.fieldMachine?.send({ type: 'BASELINE' });

        const constraint = this.getFieldConstraint();
        if (constraint && this.fieldMachine) {
            createFieldHandleExtras(this.fieldMachine).setConstraint(constraint);
        }
    }
}
