import { Component, Machine } from '../../Client';
import { getFieldMessages } from '../Form/src/form.fields';
import { trimArraySuffix } from '../Form/src/form.path';
import {
    getFieldMachinesFor,
    getFormMachineFor,
    registerFieldMachineForForm,
    unregisterFieldMachineForForm,
} from '../Form/src/form.registry';
import { createFormValues } from '../Form/src/form.values';
import { FIELD_VALUE_CHANGE_EVENT } from './src/field.dom';
import {
    FIELD_DEPENDENCY_CHANGE_EVENT,
    connectField,
    type FieldClientApi,
    type FieldDependencyChangeDetail,
} from './src/field.handle';
import { machine } from './src/field.machine';
import { isPointerPressed, whenPointerReleased } from './src/field.pointer';
import { splitProps } from './src/field.props';
import {
    registerFieldMachine,
    unregisterFieldMachine,
    type FieldMachine,
} from './src/field.registry';
import type {
    FieldProps,
    IndicatorType,
    ValidateDetails,
    ValidateResult,
    ValidityMatch,
} from './src/field.types';
import { toErrorArray } from './src/field.utils';
import { getComparableFieldValue, getCurrentFieldValue, type FieldValue } from './src/field.value';

export type { FieldClientApi, FieldDependencyChangeDetail, FieldHandle } from './src/field.handle';
export type { FieldProps } from './src/field.types';
export type { FieldValue } from './src/field.value';

export class Field extends Component<FieldProps, FieldClientApi> {
    static componentName = 'field';

    /** The `validate` a consumer set; the machine itself runs {@link validate}, which adds the form's. */
    private ownValidate: FieldProps['validate'];
    private cleanups: Array<() => void> = [];
    private cancelDeferredRender: (() => void) | undefined;

    /** The base class types its machine as `Machine<any>`. */
    private get fieldMachine(): FieldMachine {
        return this.machine;
    }

    initMachine(props: FieldProps) {
        const [machineProps] = splitProps(props);
        this.ownValidate = machineProps.validate;

        const createdMachine = new Machine(machine, {
            ...machineProps,
            validate: details => this.validate(details),
        });

        const rootEl = this.hydrator.query('root');
        registerFieldMachine(rootEl, createdMachine);
        registerFieldMachineForForm(rootEl, createdMachine);
        return createdMachine;
    }

    initApi(): FieldClientApi {
        return connectField(this.fieldMachine);
    }

    init() {
        super.init();
        this.listenToDependencies();
    }

    updateProps(newProps: Partial<FieldProps>) {
        const { validate, ...rest } = newProps;
        if ('validate' in newProps) this.ownValidate = validate;
        super.updateProps(rest);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.getControlProps(controlEl));

        const helperTextEl = this.hydrator.query('helperText');
        if (helperTextEl) this.spreadProps(helperTextEl, this.api.getHelperTextProps());

        // Showing or hiding texts shifts the layout. Pressing a button blurs the field on mousedown,
        // so doing it now would move the button away from under the pointer and lose the click.
        if (isPointerPressed()) {
            this.cancelDeferredRender ??= whenPointerReleased(() => {
                this.cancelDeferredRender = undefined;
                this.render();
            });
            return;
        }

        this.spreadPropsByOptionalValue('errorText', ({ el, value }) => {
            // the error text without a `match` shows the messages, a narrowed one keeps its own text
            if (value === undefined && this.api.errors.length > 0) {
                el.textContent = this.api.errors.join(' ');
            }
            return this.api.getErrorTextProps({ match: value as ValidityMatch | undefined });
        });

        this.spreadPropsByValue('indicator', ({ value }) =>
            this.api.getIndicatorProps({ type: value as IndicatorType })
        );
    }

    destroy() {
        const rootEl = this.fieldMachine.refs.get('rootEl');
        if (rootEl) unregisterFieldMachine(rootEl);
        unregisterFieldMachineForForm(rootEl, this.fieldMachine);

        this.cancelDeferredRender?.();
        for (const cleanup of this.cleanups.splice(0)) cleanup();
        super.destroy();
    }

    /**
     * Zag's props differ per element only in `readOnly`, and the control part is the one place a
     * bare native element is wired up by the field itself (primitives bring their own control).
     */
    private getControlProps(controlEl: HTMLElement) {
        switch (controlEl.localName) {
            case 'input':
                return this.api.getInputProps();
            case 'textarea':
                return this.api.getTextareaProps();
            case 'select':
                return this.api.getSelectProps();
            default:
                return this.api.getControlProps();
        }
    }

    /** What the machine validates with: the form's messages for this field, then the consumer's own. */
    private validate(details: ValidateDetails): ValidateResult | Promise<ValidateResult> {
        const formMachine = getFormMachineFor(this.fieldMachine.refs.get('rootEl'));
        const formMessages = formMachine
            ? getFieldMessages(formMachine, this.fieldMachine.prop('name'))
            : [];
        const own = this.ownValidate?.(details);

        if (isPromise(own)) return own.then(result => [...formMessages, ...toErrorArray(result)]);
        return [...formMessages, ...toErrorArray(own)];
    }

    /**
     * `listenTo`: when a named sibling field in the same form changes value, this field announces
     * `fluid-primitives:field:dependencychange` for consumer DOM reactions and treats it like a
     * change of its own, so it revalidates under the same rules as when its user types.
     *
     * A sibling's registration may come later than this field's (the form can hydrate after its
     * fields), so subscribing is retried whenever a form registers, until every name resolved.
     */
    private listenToDependencies() {
        const listenTo = this.fieldMachine.prop('listenTo');
        const rootEl = this.fieldMachine.refs.get('rootEl');
        const formEl = rootEl?.closest('form');
        if (!listenTo?.length || !rootEl || !formEl) return;

        const names = listenTo.map(trimArraySuffix);
        const pending = new Set(names);

        const readSibling = (sibling: FieldMachine): FieldValue =>
            getCurrentFieldValue(
                sibling.refs.get('rootEl'),
                sibling.prop('name'),
                sibling.prop('defaultValue')
            );

        const announce = () => {
            const dependencies: Record<string, FieldValue> = {};
            listenTo.forEach((name, index) => {
                const sibling = getFieldMachinesFor(rootEl).get(names[index]);
                dependencies[name] = sibling ? readSibling(sibling) : null;
            });

            const detail: FieldDependencyChangeDetail = {
                name: this.fieldMachine.prop('name'),
                dependencies,
                values: createFormValues(new FormData(formEl)),
            };
            rootEl.dispatchEvent(
                new CustomEvent(FIELD_DEPENDENCY_CHANGE_EVENT, { bubbles: true, detail })
            );
            this.fieldMachine.send({
                type: 'CONTROL.CHANGE',
                value: getComparableFieldValue(
                    rootEl,
                    this.fieldMachine.prop('name'),
                    this.fieldMachine.prop('defaultValue')
                ),
            });
        };

        const trySubscribe = () => {
            for (const name of Array.from(pending)) {
                const siblingRootEl = getFieldMachinesFor(rootEl).get(name)?.refs.get('rootEl');
                if (!siblingRootEl) continue;

                siblingRootEl.addEventListener(FIELD_VALUE_CHANGE_EVENT, announce);
                this.cleanups.push(() =>
                    siblingRootEl.removeEventListener(FIELD_VALUE_CHANGE_EVENT, announce)
                );
                pending.delete(name);
            }

            if (pending.size === 0) {
                formEl.removeEventListener('fluid-primitives:form:registered', trySubscribe);
            }
        };

        trySubscribe();
        if (pending.size > 0) {
            formEl.addEventListener('fluid-primitives:form:registered', trySubscribe);
            this.cleanups.push(() =>
                formEl.removeEventListener('fluid-primitives:form:registered', trySubscribe)
            );
        }
    }
}

function isPromise(value: unknown): value is Promise<ValidateResult> {
    return typeof value === 'object' && value !== null && 'then' in value;
}
