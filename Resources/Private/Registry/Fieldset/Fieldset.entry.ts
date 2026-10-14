import { mountAll } from 'fluid-primitives';
import { Fieldset } from 'fluid-primitives/fieldset';

mountAll('ui:fieldset', ({ props }) => {
    const fieldset = new Fieldset(props);
    fieldset.init();
    return fieldset;
});
