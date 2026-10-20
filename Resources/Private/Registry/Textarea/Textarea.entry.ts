import { mountAll } from 'fluid-primitives';
import { Textarea } from 'fluid-primitives/textarea';

mountAll('ui:textarea', ({ props }) => new Textarea(props).init());
