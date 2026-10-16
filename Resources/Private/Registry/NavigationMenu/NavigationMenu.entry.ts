import { mountAll } from 'fluid-primitives';
import { NavigationMenu } from 'fluid-primitives/navigation-menu';

mountAll('ui:navigationMenu', ({ props }) => new NavigationMenu(props).init());
