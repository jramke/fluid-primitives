import { mountAll } from 'fluid-primitives';
import { Textarea } from 'fluid-primitives/textarea';

mountAll('ui:textarea', ({ props }) => {
    const textarea = new Textarea(props);
    textarea.init();
    return textarea;
});
