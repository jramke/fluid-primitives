import { mountAll } from 'fluid-primitives';
import { Collapsible } from 'fluid-primitives/collapsible';

mountAll('ui:collapsible', ({ props }) => new Collapsible(props).init());
