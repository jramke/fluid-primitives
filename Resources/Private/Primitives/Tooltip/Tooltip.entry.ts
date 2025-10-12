import { initAllComponentInstances } from '../../Client';
import { Tooltip } from './Tooltip';

(() => {
	initAllComponentInstances('tooltip', ({ props }) => {
		const tooltip = new Tooltip(props);
		tooltip.init();
		return tooltip;
	});
})();
