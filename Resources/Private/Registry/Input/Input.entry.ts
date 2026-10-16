import { mountAll } from 'fluid-primitives';
import { Input } from 'fluid-primitives/input';

mountAll('ui:input', ({ props }) => new Input(props).init());
