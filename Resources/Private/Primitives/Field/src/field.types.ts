import type { EventObject } from '@zag-js/core';
import type { PropTypes } from '@zag-js/types';
import type { FormMachine } from '../../Form/src/form.registry';
import type { FieldHandle } from './field.handle';
import type { FieldValue } from './field.value';

export interface FieldProps {
    id: string;
    /** Ids of the parts other elements reference: `label`, `control`, `error`, `description`. */
    ids?: Record<string, string>;
    name: string;
    invalid?: boolean;
    required?: boolean;
    disabled?: boolean;
    readOnly?: boolean;
    defaultValue?: unknown;
    /**
     * Names of sibling fields within the same form whose value changes this field should react
     * to - see field.machine.ts's `setupDependencyListeners`.
     */
    listenTo?: string[];
}

export interface FieldSchema {
    props: FieldProps;
    context: {
        invalid: boolean;
        required: boolean;
        disabled: boolean;
        readOnly: boolean;
        formMachine: FormMachine | null;
        describeIds: string | undefined;
        hasDescription: boolean;
        value: FieldValue;
        initialValue: FieldValue;
        errors: string[];
        touched: boolean;
        dirty: boolean;
        blurred: boolean;
    };
    state: 'ready';
    event: EventObject;
    action: string;
    effect: string;
}

export interface FieldApi extends FieldHandle {
    getRootProps(): PropTypes['element'];
    getLabelProps(): PropTypes['label'];
    getControlProps(): PropTypes['element'];
    getErrorProps(): PropTypes['element'];
    getDescriptionProps(): PropTypes['element'];
}
