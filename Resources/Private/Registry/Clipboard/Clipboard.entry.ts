import { mountAll } from 'fluid-primitives';
import { Clipboard } from 'fluid-primitives/clipboard';

mountAll('ui:clipboard', ({ props }) => {
    const clipboard = new Clipboard(props);
    clipboard.init();
    return clipboard;
});
