import * as select from '@zag-js/select';
import { Component, Machine, mergeProps, normalizeProps } from '../../Client';
import { getListCollectionFromHydrationData } from '../../Client/src/lib/hydration';

export class Select extends Component<select.Props, select.Api> {
	name = 'select';

	initMachine(props: select.Props): Machine<any> {
		return new Machine(select.machine, {
			...props,
			get collection() {
				return getListCollectionFromHydrationData<any>(props.collection);
			},
		});
	}

	initApi() {
		return select.connect(this.machine.service, normalizeProps);
	}

	render = () => {
		const rootEl = this.getElement('root');
		if (rootEl) this.spreadProps(rootEl, this.api.getRootProps());

		const controlEl = this.getElement('control');
		if (controlEl) this.spreadProps(controlEl, this.api.getControlProps());

		const hiddenSelectEl = this.getElement('hiddenSelect');
		if (hiddenSelectEl) this.spreadProps(hiddenSelectEl, this.api.getHiddenSelectProps());

		const labelEl = this.getElement('label');
		if (labelEl) this.spreadProps(labelEl, this.api.getLabelProps());

		const triggerEl = this.getElement('trigger');
		if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

		const positionerEl = this.getElement('positioner');
		if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

		const contentEl = this.getElement('content');
		if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

		const valueTextEl = this.getElement('valueText');
		if (valueTextEl)
			this.spreadProps(
				valueTextEl,
				mergeProps(this.api.getValueTextProps(), {
					children: this.api.valueAsString || valueTextEl.dataset.placeholder,
				})
			);

		const itemGroupEls = this.getElements('itemGroup');
		itemGroupEls.forEach(itemGroupEl => {
			this.spreadProps(
				itemGroupEl,
				this.api.getItemGroupProps({ id: itemGroupEl.dataset.id! })
			);
			const itemGroupLabelEl = this.getElement('itemGroupLabel', itemGroupEl);
			if (itemGroupLabelEl) {
				this.spreadProps(
					itemGroupLabelEl,
					this.api.getItemGroupLabelProps({ htmlFor: itemGroupEl.dataset.id! })
				);
			}
		});

		const itemEls = this.getElements('item');
		itemEls.forEach(itemEl => {
			const item = this.api.collection.find(itemEl.dataset.value);
			if (item) {
				this.spreadProps(itemEl, this.api.getItemProps({ item }));
			}
		});

		const itemTextEls = this.getElements('itemText');
		itemTextEls.forEach(itemTextEl => {
			const item = this.api.collection.find(itemTextEl.dataset.value);
			if (item) {
				this.spreadProps(itemTextEl, this.api.getItemTextProps({ item }));
			}
		});

		const itemIndicatorEls = this.getElements('itemIndicator');
		itemIndicatorEls.forEach(itemIndicatorEl => {
			const item = this.api.collection.find(itemIndicatorEl.dataset.value);
			if (item) {
				this.spreadProps(itemIndicatorEl, this.api.getItemIndicatorProps({ item }));
			}
		});

		const clearTriggerEl = this.getElement('clearTrigger');
		if (clearTriggerEl) this.spreadProps(clearTriggerEl, this.api.getClearTriggerProps());

		const indicatorEl = this.getElement('indicator');
		if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());
	};
}
