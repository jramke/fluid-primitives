import { Component, Machine, normalizeProps } from '../../Client';
import { connect } from './src/field-array.connect';
import { machine } from './src/field-array.machine';
import type { FieldArrayApi, FieldArrayProps } from './src/field-array.types';
export type { FieldArrayHydrationProps } from './FieldArray.hydration';

export type {
    FieldArrayAnnounceInfo,
    FieldArrayAnnounceTranslation,
    FieldArrayApi,
    FieldArrayProps,
} from './src/field-array.types';

export class FieldArray extends Component<FieldArrayProps, FieldArrayApi> {
    static componentName = 'fieldArray';

    initMachine(props: FieldArrayProps): Machine<any> {
        return new Machine(machine, props);
    }

    initApi(): FieldArrayApi {
        return connect(this, normalizeProps);
    }

    render() {
        const addTriggerEl = this.getElement('addTrigger');
        if (addTriggerEl) {
            // `aria-disabled`, not the real `disabled` attribute - a genuinely disabled button
            // can't hold focus, which would fight `findFocusTargetAfterRemoval`'s own focus
            // restoration the moment a removal brings the array down to `minItems` (the newly
            // focused `removeTrigger` would immediately lose focus again as it becomes disabled
            // itself). `append`/`remove` already no-op past their bound, so nothing but the visual/
            // AT-announced state depends on this attribute.
            this.spreadProps(addTriggerEl, this.api.getAddTriggerProps());
        }

        for (const removeTriggerEl of this.getElements<HTMLElement>('removeTrigger')) {
            const index = Number(removeTriggerEl.dataset.value);
            if (Number.isNaN(index)) continue;
            this.spreadProps(removeTriggerEl, this.api.getRemoveTriggerProps(index));
        }

        const emptyStateEl = this.getElement('emptyState');
        if (emptyStateEl) {
            emptyStateEl.hidden = this.getElements('item').length > 0;
        }
    }
}
