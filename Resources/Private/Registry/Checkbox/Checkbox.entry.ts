import { mountAll } from 'fluid-primitives';
import { Checkbox } from 'fluid-primitives/checkbox';

mountAll('ui:checkbox', ({ props }) => new Checkbox(props).init());
