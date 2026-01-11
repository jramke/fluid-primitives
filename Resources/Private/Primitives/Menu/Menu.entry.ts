import { mount } from '../../Client';
import { Menu } from './Menu';

(() => {
	mount('menu', ({ props }) => {
		const menu = new Menu(props);
		menu.init();
		return menu;
	});
})();
