import * as clipboard from '@zag-js/clipboard';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';
import { resolveTranslations, type ClipboardTranslationTexts } from './src/clipboard.translations';

// `triggerLabel` is a function of the copied state in Zag, a text per state on the wire.
const clipboardPropConverters = {
    translations: (texts: ClipboardTranslationTexts | undefined) => resolveTranslations(texts),
} satisfies ClientPropConverterMap;

registerClientPropConverters('clipboard', clipboardPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        clipboard: ConverterMachineProps<typeof clipboardPropConverters>;
    }
}

export class Clipboard extends Component<clipboard.Props, clipboard.Api> {
    static componentName = 'clipboard';

    initMachine(props: clipboard.Props): Machine<any> {
        const [machineProps] = clipboard.contextProps(props);
        return new Machine(clipboard.machine, machineProps);
    }

    initApi() {
        return clipboard.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

        const labelEl = this.hydrator.query('label');
        if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

        const controlEl = this.hydrator.query('control');
        if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

        const inputEl = this.hydrator.query('input');
        if (inputEl) this.spreadProps(inputEl, this.api.getInputProps());

        this.spreadPropsByValue('indicator', ({ value }) =>
            this.api.getIndicatorProps({ copied: value === 'copied' })
        );

        const triggerEl = this.hydrator.query('trigger');
        if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());
    }
}
