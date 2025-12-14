import { initAllComponentInstances } from '../../Client';
import { RadioGroup } from './RadioGroup';

(() => {
	initAllComponentInstances('radio-group', ({ props }) => {
		const radioGroup = new RadioGroup(props);
		radioGroup.init();
		return radioGroup;
	});
})();
