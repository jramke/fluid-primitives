import type { Service } from '@zag-js/core';
import { Component } from '.';
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
type FieldProps = (typeof fieldProps)[number];

const fieldAccessors: Record<FieldProps, (s: Service<any>) => boolean | string | undefined> = {
    disabled: s => s.context.get('disabled'),
    readOnly: s => s.context.get('readOnly'),
    required: s => s.context.get('required'),
    invalid: s => s.context.get('invalid'),
    name: s => s.prop('name'),
};

function isBooleanFieldProp(prop: FieldProps): prop is (typeof booleanFieldProps)[number] {
    return (booleanFieldProps as readonly string[]).includes(prop);
}

export abstract class FieldAwareComponent<Props, Api> extends Component<Props, Api> {
    protected subscribedToField = false;
    protected fieldMachine: FieldMachine | undefined;
    protected closestField: HTMLElement | null = null;

    protected abstract propsWithField(props: Partial<Props>, fieldMachine: FieldMachine): Props;

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
                    let propsToUpdate: Partial<Record<FieldProps, boolean | string | undefined>> =
                        {};

                    for (const prop of fieldProps) {
                        if (isBooleanFieldProp(prop)) {
                            const newValue = !!fieldAccessors[prop](snapshot);
                            const currentValue = !!this.machine.prop(prop);

                            if (newValue !== currentValue) {
                                propsToUpdate[prop] = newValue;
                            }
                            continue;
                        }

                        const newValue = fieldAccessors[prop](snapshot);
                        const currentValue = this.machine.prop(prop);

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
