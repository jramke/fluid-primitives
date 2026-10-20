import { mountAll } from 'fluid-primitives';
import { Switch } from 'fluid-primitives/switch';

mountAll('ui:switch', ({ props }) => new Switch(props).init());
