import { mountAll } from 'fluid-primitives';
import { ScrollArea } from 'fluid-primitives/scroll-area';

mountAll('ui:scrollArea', ({ props }) => new ScrollArea(props).init());
