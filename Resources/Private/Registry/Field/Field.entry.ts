import { mountAll } from 'fluid-primitives';
import { Field } from 'fluid-primitives/field';

mountAll('ui:field', ({ props }) => {
    const field = new Field(props);
    field.init();
    return field;
});
