import { mountAll } from 'fluid-primitives';
import { Slider } from 'fluid-primitives/slider';

mountAll('ui:slider', ({ props }) => new Slider(props).init());
