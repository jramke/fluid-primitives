import { mountAll } from 'fluid-primitives';
import { Toc } from 'fluid-primitives/toc';

mountAll('ui:toc', ({ props }) => new Toc(props).init());
