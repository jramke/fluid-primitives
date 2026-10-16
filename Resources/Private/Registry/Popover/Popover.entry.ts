import { mountAll } from 'fluid-primitives';
import { Popover } from 'fluid-primitives/popover';

mountAll('ui:popover', ({ props }) => new Popover(props).init());
