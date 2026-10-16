import { mountAll } from 'fluid-primitives';
import { NumberInput } from 'fluid-primitives/number-input';

mountAll('ui:numberInput', ({ props }) => new NumberInput(props).init());
