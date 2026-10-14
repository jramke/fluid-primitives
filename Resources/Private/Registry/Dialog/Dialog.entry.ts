import { mountAll } from 'fluid-primitives';
import { Dialog } from 'fluid-primitives/dialog';

mountAll('ui:dialog', ({ props }) => {
    const dialog = new Dialog(props);
    dialog.init();
    return dialog;
});
