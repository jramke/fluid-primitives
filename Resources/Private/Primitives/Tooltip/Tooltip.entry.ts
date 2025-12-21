import { mount } from '../../Client';
import { Tooltip } from './Tooltip';

(() => {
	mount('tooltip', ({ props }) => {
		const tooltip = new Tooltip(props);
		tooltip.init();
		return tooltip;
	});
})();
