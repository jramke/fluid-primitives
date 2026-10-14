import { mountAll } from 'fluid-primitives';
import { Tooltip } from 'fluid-primitives/tooltip';

mountAll('ui:tooltip', ({ props }) => {
    const tooltip = new Tooltip(props);
    tooltip.init();
    return tooltip;
});
