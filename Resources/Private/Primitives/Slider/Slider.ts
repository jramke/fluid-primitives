import * as slider from '@zag-js/slider';
import {
    FieldAwareComponent,
    Machine,
    mergeProps,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import type { FieldClientApi } from '../Field/src/field.handle';

// PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
// prop (see WireTypeResolver), so `thumbSize` resolves to `unknown` on the wire - this converter
// just tells TS what it actually is (a real @zag-js/slider `{ width, height }` size) rather than
// transforming the value itself.
const sliderPropConverters = {
    thumbSize: (thumbSize: slider.Props['thumbSize']) => thumbSize,
} satisfies ClientPropConverterMap;

registerClientPropConverters('slider', sliderPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        slider: ConverterMachineProps<typeof sliderPropConverters>;
    }
}

export class Slider extends FieldAwareComponent<slider.Props, slider.Api> {
    static componentName = 'slider';

    // No `required` here - @zag-js/slider has no such prop (a slider always carries some
    // value, so "required" has no meaningful unset state to enforce).
    propsWithField(props: slider.Props, field: FieldClientApi): slider.Props {
        return {
            ...props,
            disabled: props.disabled ?? field.disabled,
            readOnly: props.readOnly ?? field.readOnly,
            invalid: props.invalid ?? field.invalid,
            name: props.name ?? field.name,
        };
    }

    initMachine(props: slider.Props): Machine<any> {
        props = this.withFieldProps(props);
        const [machineProps] = slider.splitProps(props);
        return new Machine(slider.machine, machineProps);
    }

    initApi() {
        return slider.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.subscribeToFieldService();

        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const valueTextEl = this.hydrator.query('valueText');
        if (valueTextEl) {
            this.spreadProps(valueTextEl, this.api.getValueTextProps());
            valueTextEl.textContent = this.api.value.join(' - ');
        }

        const trackEl = this.hydrator.query('track');
        if (trackEl) this.spreadProps(trackEl, this.api.getTrackProps());

        const rangeEl = this.hydrator.query('range');
        if (rangeEl) this.spreadProps(rangeEl, this.api.getRangeProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const markerGroupEl = this.hydrator.query('markerGroup');
        if (markerGroupEl) this.spreadProps(markerGroupEl, this.api.getMarkerGroupProps());

        // hiddenInput is hydrated alongside its wrapping thumb, since it shares its index/name.
        // aria-describedby/aria-invalid aren't part of @zag-js/slider's own getThumbProps() - like
        // RadioGroup's root, each thumb is its own focusable control, so both are merged in here
        // from the surrounding Field, the same way every field-aware component does it.
        this.hydrator.queryAll('thumb').forEach(thumbEl => {
            const index = Number(thumbEl.dataset.value ?? 0);
            const name = thumbEl.dataset.name;
            const thumbProps = mergeProps(this.api.getThumbProps({ index, name }), {
                'aria-describedby': this.field?.ariaDescribedby,
                'aria-invalid': this.field?.invalid || undefined,
            });
            this.spreadProps(thumbEl, thumbProps);

            const hiddenInputEl = this.hydrator.query<HTMLInputElement>('hiddenInput', thumbEl);
            if (hiddenInputEl) {
                this.spreadProps(hiddenInputEl, this.api.getHiddenInputProps({ index, name }));
            }
        });

        this.spreadPropsByValue('marker', ({ value }) =>
            this.api.getMarkerProps({ value: Number(value) })
        );

        this.hydrator.queryAll('draggingIndicator').forEach(el => {
            const index = Number(el.dataset.value ?? 0);
            this.spreadProps(el, this.api.getDraggingIndicatorProps({ index }));
            el.textContent = String(this.api.getThumbValue(index));
        });
    }
}
