import { mountAll } from 'fluid-primitives';
import { Select } from 'fluid-primitives/select';

mountAll('ui:select', ({ props }) => {
    const select = new Select(props);
    select.init();
    return select;
});
