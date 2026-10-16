import { mountAll } from 'fluid-primitives';
import { ToggleGroup } from 'fluid-primitives/toggle-group';

mountAll('ui:toggleGroup', ({ props }) => new ToggleGroup(props).init());
