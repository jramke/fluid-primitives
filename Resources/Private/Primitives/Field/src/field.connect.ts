/**
 * Derived from @zag-js/field of Zag.js (https://github.com/chakra-ui/zag, packages/machines/field,
 * commit 0ce8e3de63b571d37e438bcf28e64759466e56e9), MIT License, Copyright (c) 2021 Chakra UI.
 * Adapted for Fluid Primitives, the git history shows what changed from the original.
 */

import type { Service } from '@zag-js/core';
import { dataAttr } from '@zag-js/dom-query';
import type { NormalizeProps, PropTypes } from '@zag-js/types';
import { match } from '@zag-js/utils';
import { parts } from './field.anatomy';
import * as dom from './field.dom';
import type {
    ErrorTextProps,
    FieldApi,
    FieldSchema,
    FieldState,
    IndicatorProps,
} from './field.types';
import { composeDescribedBy, isErrorMatch } from './field.utils';

export function connect<T extends PropTypes>(
    service: Service<FieldSchema>,
    normalize: NormalizeProps<T>
): FieldApi<T> {
    const { send, context, prop, computed, scope } = service;

    const disabled = computed('disabled');
    const invalid = computed('invalid');
    const valid = computed('valid');
    const required = !!prop('required');
    const readOnly = !!prop('readOnly');

    const focused = !disabled && context.get('focused');
    const touched = context.get('touched');
    const dirty = context.get('dirty');
    const filled = context.get('filled');
    const validating = context.get('validating');

    const ids = {
        root: dom.getRootId(scope),
        control: dom.getControlId(scope),
        label: dom.getLabelId(scope),
        errorText: dom.getErrorTextId(scope),
        helperText: dom.getHelperTextId(scope),
    };

    const ariaDescribedby = composeDescribedBy({
        helperTextId: ids.helperText,
        hasHelperText: context.get('hasHelperText'),
        errorTextIds: context.get('errorTextIds'),
    });

    // -----------------------------------------------------------------------------
    // State getters: pure, serializable per-part state, independent of `normalize`
    // -----------------------------------------------------------------------------

    function getFieldState(): FieldState {
        return {
            disabled,
            invalid,
            valid,
            required,
            readOnly,
            touched,
            dirty,
            filled,
            focused,
            validating,
        };
    }

    function getErrorTextState(props: ErrorTextProps = {}) {
        const shown = isErrorMatch(props.match, {
            validity: context.get('validity'),
            invalid,
            disabled,
        });
        return { ...getFieldState(), hidden: !shown };
    }

    function getIndicatorState(props: IndicatorProps) {
        const fieldState = getFieldState();
        const shown = match(props.type, {
            required: () => fieldState.required,
            invalid: () => fieldState.invalid,
            valid: () => fieldState.valid === true,
            validating: () => fieldState.validating,
        });
        return { ...fieldState, type: props.type, hidden: !shown };
    }

    function getControlBaseProps() {
        const fieldState = getFieldState();
        return {
            ...parts.control.attrs(scope.id),
            id: ids.control,
            dir: prop('dir'),
            disabled: fieldState.disabled,
            required: fieldState.required,
            'aria-invalid': fieldState.invalid || undefined,
            'aria-describedby': ariaDescribedby,
            ...getDataAttrs(fieldState),
        };
    }

    const api: FieldApi<T> = {
        ids,
        disabled,
        invalid,
        valid,
        required,
        readOnly,
        focused,
        touched,
        dirty,
        filled,
        validating,
        errors: context.get('errors'),
        validity: context.get('validity'),
        ariaDescribedby,

        validate() {
            send({ type: 'VALIDATE' });
        },

        clearErrors() {
            send({ type: 'ERRORS.CLEAR' });
        },

        reset() {
            send({ type: 'RESET' });
        },

        getRootState: getFieldState,
        getRootProps() {
            return normalize.element({
                ...parts.root.attrs(scope.id),
                dir: prop('dir'),
                /** The name of the field. */
                'data-name': prop('name'),
                ...getDataAttrs(getFieldState()),
            });
        },

        getLabelState: getFieldState,
        getLabelProps() {
            return normalize.label({
                ...parts.label.attrs(scope.id),
                id: ids.label,
                dir: prop('dir'),
                htmlFor: ids.control,
                ...getDataAttrs(getFieldState()),
            });
        },

        getControlState: getFieldState,
        getControlProps() {
            return normalize.element({
                ...getControlBaseProps(),
            });
        },

        getInputProps() {
            return normalize.input({
                ...getControlBaseProps(),
                readOnly: getFieldState().readOnly,
            });
        },

        getTextareaProps() {
            return normalize.textarea({
                ...getControlBaseProps(),
                readOnly: getFieldState().readOnly,
            });
        },

        getSelectProps() {
            return normalize.select({
                ...getControlBaseProps(),
            });
        },

        getHelperTextState: getFieldState,
        getHelperTextProps() {
            return normalize.element({
                ...parts.helperText.attrs(scope.id),
                id: ids.helperText,
                dir: prop('dir'),
                ...getDataAttrs(getFieldState()),
            });
        },

        getErrorTextState,
        getErrorTextProps(props: ErrorTextProps = {}) {
            const errorTextState = getErrorTextState(props);
            return normalize.element({
                ...parts.errorText.attrs(scope.id),
                id: dom.getErrorTextId(scope, props.match, props.id),
                dir: prop('dir'),
                hidden: errorTextState.hidden,
                'aria-live': 'polite',
                ...getDataAttrs(errorTextState),
            });
        },

        getIndicatorState,
        getIndicatorProps(props) {
            const indicatorState = getIndicatorState(props);
            return normalize.element({
                ...parts.indicator.attrs(scope.id),
                dir: prop('dir'),
                /** The state the indicator reflects. */
                'data-type': indicatorState.type,
                'aria-hidden': true,
                hidden: indicatorState.hidden,
                ...getDataAttrs(indicatorState),
            });
        },
    };

    return api;
}

function getDataAttrs(state: FieldState) {
    return {
        /** Present when the field is disabled, or it is inside a disabled fieldset. */
        'data-disabled': dataAttr(state.disabled),
        /** Present when the field is invalid: a native constraint failed, or `validate`, the form or the server reported an error. */
        'data-invalid': dataAttr(state.invalid),
        /** Present when the field has been validated and passed. */
        'data-valid': dataAttr(state.valid === true),
        /** Present when the field is required. */
        'data-required': dataAttr(state.required),
        /** Present when the field is read-only. */
        'data-readonly': dataAttr(state.readOnly),
        /** Present once the user has left the field at least once. */
        'data-touched': dataAttr(state.touched),
        /** Present when the value differs from the one the field started with. It goes away again when the user reverts the edit. */
        'data-dirty': dataAttr(state.dirty),
        /** Present when the field has a value. */
        'data-filled': dataAttr(state.filled),
        /** Present when focus is inside the field. */
        'data-focus': dataAttr(state.focused),
    };
}
