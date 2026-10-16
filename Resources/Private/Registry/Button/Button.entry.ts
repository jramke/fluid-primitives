import { mountAll } from 'fluid-primitives';
import { Button } from 'fluid-primitives/button';

mountAll('ui:button', ({ props }) => new Button(props).init());
