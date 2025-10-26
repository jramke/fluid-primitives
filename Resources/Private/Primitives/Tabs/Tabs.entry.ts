import { initAllComponentInstances } from '../../Client';
import { Tabs } from './Tabs';

(() => {
	initAllComponentInstances('tabs', ({ props }) => {
		const tabs = new Tabs(props);
		tabs.init();
		return tabs;
	});
})();
