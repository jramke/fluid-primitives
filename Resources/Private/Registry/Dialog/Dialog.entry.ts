import { mountAll } from 'fluid-primitives';
import { Dialog } from 'fluid-primitives/dialog';

mountAll('ui:dialog', ({ props }) => new Dialog(props).init());
