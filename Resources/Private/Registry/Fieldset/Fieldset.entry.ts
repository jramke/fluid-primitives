import { mountAll } from 'fluid-primitives';
import { Fieldset } from 'fluid-primitives/fieldset';

mountAll('ui:fieldset', ({ props }) => new Fieldset(props).init());
