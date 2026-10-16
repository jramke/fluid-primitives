import { mountAll } from 'fluid-primitives';
import { Select } from 'fluid-primitives/select';

mountAll('ui:select', ({ props }) => new Select(props).init());
