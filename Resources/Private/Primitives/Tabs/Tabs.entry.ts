import { mount } from '../../Client';
import { Tabs } from './Tabs';

(() => {
	mount('tabs', ({ props }) => {
		const tabs = new Tabs(props);
		tabs.init();
		return tabs;
	});
})();
