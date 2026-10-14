import { mountAll } from 'fluid-primitives';
import { CheckboxGroup } from 'fluid-primitives/checkbox-group';

mountAll('ui:checkboxGroup', ({ props }) => {
    const checkboxGroup = new CheckboxGroup(props);
    checkboxGroup.init();
    return checkboxGroup;
});
