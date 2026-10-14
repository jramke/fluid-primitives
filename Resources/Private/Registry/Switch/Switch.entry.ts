import { mountAll } from 'fluid-primitives';
import { Switch } from 'fluid-primitives/switch';

mountAll('ui:switch', ({ props }) => {
    const switchInstance = new Switch(props);
    switchInstance.init();
    return switchInstance;
});
