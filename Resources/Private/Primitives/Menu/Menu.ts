import * as menu from '@zag-js/menu';
import { Component, Machine, normalizeProps } from '../../Client';

// Registry for managing parent/child menu relationships
const menuRegistry = new Map<string, Menu>();

export function getMenuById(id: string): Menu | undefined {
	return menuRegistry.get(id);
}

export function registerMenu(id: string, menuInstance: Menu): void {
	menuRegistry.set(id, menuInstance);
}

export function unregisterMenu(id: string): void {
	menuRegistry.delete(id);
}

export class Menu extends Component<menu.Props, menu.Api> {
	static name = 'menu';
	private parentMenuId: string | null = null;
	private childMenus: Map<string, Menu> = new Map();

	initMachine(props: menu.Props): Machine<any> {
		// Store parent menu reference if provided
		if ((props as any).parentMenu) {
			this.parentMenuId = (props as any).parentMenu;
		}

		return new Machine(menu.machine, {
			...props,
			'aria-label': (props as any).ariaLabel,
			positioning: {
				gutter: 6,
				...props.positioning,
			},
		});
	}

	initApi() {
		return menu.connect(this.machine.service, normalizeProps);
	}

	init() {
		// Register this menu in the global registry
		registerMenu(this.machine.scope.id, this);

		// Set up parent/child relationships
		if (this.parentMenuId) {
			const parentMenu = getMenuById(this.parentMenuId);
			if (parentMenu) {
				this.setParentMenu(parentMenu);
			}
		}

		super.init();
	}

	setParentMenu(parent: Menu) {
		this.api.setParent(parent.machine.service);
		parent.api.setChild(this.machine.service);
		parent.childMenus.set(this.machine.scope.id, this);
	}

	getChildMenu(id: string): Menu | undefined {
		return this.childMenus.get(id);
	}

	destroy = () => {
		unregisterMenu(this.machine.scope.id);
		super.destroy();
	};

	render() {
		const triggerEl = this.getElement('trigger');
		if (triggerEl) this.spreadProps(triggerEl, this.api.getTriggerProps());

		const contextTriggerEl = this.getElement('context-trigger');
		if (contextTriggerEl) this.spreadProps(contextTriggerEl, this.api.getContextTriggerProps());

		const positionerEl = this.getElement('positioner');
		if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

		const arrowEl = this.getElement('arrow');
		if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

		const arrowTipEl = this.getElement('arrow-tip');
		if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

		const contentEl = this.getElement('content');
		if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

		// Handle regular menu items
		const itemEls = this.getElements('item');
		itemEls.forEach(itemEl => {
			const value = itemEl.dataset.value;
			if (value) {
				const closeOnSelect = itemEl.dataset.closeOnSelect !== 'false';
				this.spreadProps(
					itemEl,
					this.api.getItemProps({
						value,
						disabled: itemEl.dataset.disabled === 'true',
						closeOnSelect,
						valueText: itemEl.dataset.valueText,
					})
				);
			}
		});

		// Handle trigger items (for nested menus)
		const triggerItemEls = this.getElements('trigger-item');
		triggerItemEls.forEach(triggerItemEl => {
			const value = triggerItemEl.dataset.value;
			if (value) {
				const childMenu = this.getChildMenu(value);
				if (childMenu) {
					this.spreadProps(
						triggerItemEl,
						this.api.getTriggerItemProps(childMenu.api)
					);
				} else {
					// Fallback: treat as a regular item until child menu is registered
					this.spreadProps(
						triggerItemEl,
						this.api.getItemProps({
							value,
							disabled: triggerItemEl.dataset.disabled === 'true',
						})
					);
				}
			}
		});

		// Handle option items (checkbox/radio)
		const optionItemEls = this.getElements('option-item');
		optionItemEls.forEach(optionItemEl => {
			const name = optionItemEl.dataset.name;
			const type = optionItemEl.dataset.type as 'checkbox' | 'radio';
			const value = optionItemEl.dataset.value;
			if (name && type && value) {
				const closeOnSelect = optionItemEl.dataset.closeOnSelect !== 'false';
				this.spreadProps(
					optionItemEl,
					this.api.getOptionItemProps({
						name,
						type,
						value,
						disabled: optionItemEl.dataset.disabled === 'true',
						closeOnSelect,
						valueText: optionItemEl.dataset.valueText,
					})
				);
			}
		});

		// Handle item groups
		const itemGroupEls = this.getElements('item-group');
		itemGroupEls.forEach(itemGroupEl => {
			const id = itemGroupEl.dataset.id;
			if (id) {
				this.spreadProps(itemGroupEl, this.api.getItemGroupProps({ id }));

				// Find and handle the group label within this group
				const itemGroupLabelEl = this.getElement('item-group-label', itemGroupEl);
				if (itemGroupLabelEl) {
					this.spreadProps(
						itemGroupLabelEl,
						this.api.getItemGroupLabelProps({ htmlFor: id })
					);
				}
			}
		});

		// Handle separators
		const separatorEls = this.getElements('separator');
		separatorEls.forEach(separatorEl => {
			this.spreadProps(separatorEl, this.api.getSeparatorProps());
		});

		// Handle item text elements
		const itemTextEls = this.getElements('item-text');
		itemTextEls.forEach(itemTextEl => {
			this.spreadProps(itemTextEl, this.api.getItemTextProps());
		});

		// Handle item indicator elements
		const itemIndicatorEls = this.getElements('item-indicator');
		itemIndicatorEls.forEach(itemIndicatorEl => {
			this.spreadProps(itemIndicatorEl, this.api.getItemIndicatorProps());
		});
	}
}
