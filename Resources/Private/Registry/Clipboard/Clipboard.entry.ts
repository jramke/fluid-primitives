import { mountAll } from 'fluid-primitives';
import { Clipboard } from 'fluid-primitives/clipboard';

mountAll('ui:clipboard', ({ props }) => new Clipboard(props).init());
