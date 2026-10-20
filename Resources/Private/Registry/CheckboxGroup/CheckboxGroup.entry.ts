import { mountAll } from 'fluid-primitives';
import { CheckboxGroup } from 'fluid-primitives/checkbox-group';

mountAll('ui:checkboxGroup', ({ props }) => new CheckboxGroup(props).init());
