import { mountAll } from 'fluid-primitives';
import { ToggleGroup } from 'fluid-primitives/toggle-group';

mountAll('ui:toggleGroup', ({ props }) => {
    const toggleGroup = new ToggleGroup(props);
    toggleGroup.init();
    return toggleGroup;
});
