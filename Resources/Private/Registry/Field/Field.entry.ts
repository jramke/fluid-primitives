import { mountAll } from 'fluid-primitives';
import { Field } from 'fluid-primitives/field';

mountAll('ui:field', ({ props }) => new Field(props).init());
