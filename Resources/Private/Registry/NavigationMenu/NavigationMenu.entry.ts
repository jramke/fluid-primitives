import { mountAll } from 'fluid-primitives';
import { NavigationMenu } from 'fluid-primitives/navigation-menu';

mountAll('ui:navigationMenu', ({ props }) => {
    const navigationMenu = new NavigationMenu(props);
    navigationMenu.init();
    return navigationMenu;
});
