import { mountAll } from 'fluid-primitives';
import { Popover } from 'fluid-primitives/popover';

mountAll('ui:popover', ({ props }) => {
    const popover = new Popover(props);
    popover.init();
    return popover;
});
