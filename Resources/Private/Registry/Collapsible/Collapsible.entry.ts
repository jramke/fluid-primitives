import { mountAll } from 'fluid-primitives';
import { Collapsible } from 'fluid-primitives/collapsible';

mountAll('ui:collapsible', ({ props }) => {
    const collapsible = new Collapsible(props);
    collapsible.init();
    return collapsible;
});
