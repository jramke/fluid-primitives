import { mountAll } from 'fluid-primitives';
import { Toggle } from 'fluid-primitives/toggle';

mountAll('ui:toggle', ({ props }) => {
    const toggle = new Toggle(props);
    toggle.init();
    return toggle;
});
