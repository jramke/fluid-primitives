import { mountAll } from 'fluid-primitives';
import { Accordion } from 'fluid-primitives/accordion';

mountAll('ui:accordion', ({ props }) => new Accordion(props).init());
