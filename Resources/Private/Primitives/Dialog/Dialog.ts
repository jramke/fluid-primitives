import * as dialog from '@zag-js/dialog';
import { Component, Machine, normalizeProps } from '../../Client';

export class Dialog extends Component<dialog.Props, dialog.Api> {
    static componentName = 'dialog';

    initMachine(props: dialog.Props): Machine<any> {
        const [machineProps] = dialog.splitProps(props);
        return new Machine(dialog.machine, machineProps);
    }

    initApi() {
        return dialog.connect(this.machine.service, normalizeProps);
    }

    render() {
        this.spreadPropsByOptionalValue('trigger', ({ value }) =>
            this.api.getTriggerProps({ value })
        );

        const backdropEl = this.hydrator.query('backdrop');
        if (backdropEl) {
            this.spreadProps(backdropEl, this.api.getBackdropProps());
        }

        const positionerEl = this.hydrator.query('positioner');
        if (positionerEl) {
            this.spreadProps(positionerEl, this.api.getPositionerProps());
        }

        const contentEl = this.hydrator.query('content');
        if (contentEl) {
            this.spreadProps(contentEl, this.api.getContentProps());
        }

        const titleEl = this.hydrator.query('title');
        if (titleEl) {
            this.spreadProps(titleEl, this.api.getTitleProps());
        }

        const descriptionEl = this.hydrator.query('description');
        if (descriptionEl) {
            this.spreadProps(descriptionEl, this.api.getDescriptionProps());
        }

        const closeTriggers = this.hydrator.queryAll('closeTrigger');
        closeTriggers.forEach(trigger => {
            this.spreadProps(trigger, this.api.getCloseTriggerProps());
        });
    }
}
