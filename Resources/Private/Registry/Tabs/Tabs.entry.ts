import { mountAll } from 'fluid-primitives';
import { Tabs } from 'fluid-primitives/tabs';

mountAll('ui:tabs', ({ props }) => new Tabs(props).init());
