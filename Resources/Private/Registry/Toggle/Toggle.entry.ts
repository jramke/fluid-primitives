import { mountAll } from 'fluid-primitives';
import { Toggle } from 'fluid-primitives/toggle';

mountAll('ui:toggle', ({ props }) => new Toggle(props).init());
