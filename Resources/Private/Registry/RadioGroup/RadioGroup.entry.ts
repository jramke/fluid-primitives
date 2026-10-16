import { mountAll } from 'fluid-primitives';
import { RadioGroup } from 'fluid-primitives/radio-group';

mountAll('ui:radioGroup', ({ props }) => new RadioGroup(props).init());
