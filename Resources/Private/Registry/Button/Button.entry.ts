import { mountAll } from 'fluid-primitives';
import { Button } from 'fluid-primitives/button';

mountAll('ui:button', ({ props }) => {
    const button = new Button(props);
    button.init();
    return button;
});
