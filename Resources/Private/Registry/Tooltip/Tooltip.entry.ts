import { mountAll } from 'fluid-primitives';
import { Tooltip } from 'fluid-primitives/tooltip';

mountAll('ui:tooltip', ({ props }) => new Tooltip(props).init());
