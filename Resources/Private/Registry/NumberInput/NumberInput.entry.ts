import { mountAll } from 'fluid-primitives';
import { NumberInput } from 'fluid-primitives/number-input';

mountAll('ui:numberInput', ({ props }) => {
    const numberInput = new NumberInput(props);
    numberInput.init();
    return numberInput;
});
