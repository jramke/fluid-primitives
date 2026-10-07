import { Component } from '.';
import { connectField, type FieldClientApi } from '../../../Primitives/Field/src/field.handle';
import {
    getFieldMachineFor,
    type FieldMachine,
} from '../../../Primitives/Field/src/field.registry';

const booleanFieldProps = ['invalid', 'disabled', 'readOnly', 'required'] as const;
// `name` is tracked reactively too (not just merged once at mount) so that
// renaming a field (e.g. Form.api.renameField(), used to keep recurring
// field rows contiguously indexed) also updates Field-aware primitives like
// NumberInput/Select/Checkbox/RadioGroup that wrap a field, not just plain
// asChild-wrapped native inputs (whose `name` attribute is already kept in
// sync directly by Field's own control props).
const fieldProps = [...booleanFieldProps, 'name'] as const;
type FieldProp = (typeof fieldProps)[number];

export abstract class FieldAwareComponent<Props, Api> extends Component<Props, Api> {
    protected subscribedToField = false;
    protected fieldMachine: FieldMachine | undefined;
    protected closestField: HTMLElement | null = null;

    protected abstract propsWithField(props: Partial<Props>, field: FieldClientApi): Props;

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

    init() {
        super.init();
        this.notifyFieldHydrated();
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

    /**
     * The field measured its starting value from the server HTML; a primitive may hydrate to a
     * different state (a defaultChecked checkbox, a Select's selected option).
     */
    private notifyFieldHydrated() {
        this.fieldMachine?.send({ type: 'BASELINE' });
    }
}
