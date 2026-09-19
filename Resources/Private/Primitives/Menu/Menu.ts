import * as menu from '@zag-js/menu';
import { Component, getComponentInstance, Machine, normalizeProps } from '../../Client';

export class Menu extends Component<menu.Props, menu.Api> {
    static componentName = 'menu';

    /**
     * `menu.triggerItem` elements found so far, keyed by `childId`. `getTriggerItemProps()` always
     * rewrites an element's `id` to the child's own trigger id, so after the first spread it no
     * longer matches `getElements('triggerItem')`'s lookup - caching it here keeps it updatable.
     */
    private triggerItemEls = new Map<string, HTMLElement>();

    initMachine(props: menu.Props): Machine<any> {
        const { parentId: _parentId, ...menuProps } = props as menu.Props & { parentId?: string };

        return new Machine(menu.machine, {
            // navigate({ href }) {
            //     window.location.href = href;
            // },
            ...menuProps,
        });
    }

    initApi() {
        return menu.connect(this.machine.service, normalizeProps);
    }

    init() {
        super.init();
        this.linkToParent();
    }

    private getParentId(): string | undefined {
        return (this.userProps as { parentId?: string } | undefined)?.parentId;
    }

    private getParentInstance(): Menu | null {
        const parentId = this.getParentId();
        return parentId ? (getComponentInstance<Menu>('menu', parentId) ?? null) : null;
    }

    /**
     * Composes two independent `Menu` instances into a submenu relationship - see `menu.root`'s
     * `parentId` prop and `menu.triggerItem`'s `childId` prop. Zag's own `setParent`/`setChild` API
     * expects live `MenuService` instances, which only exist once both components have mounted, so
     * this runs after `init()` (deferred via `setTimeout`, so it doesn't depend on mount order
     * between the two) rather than as a machine prop.
     */
    private linkToParent() {
        if (!this.getParentId()) return;

        setTimeout(() => {
            const parent = this.getParentInstance();
            if (!parent) return;

            parent.api.setChild(this.machine.service);
            this.api.setParent(parent.machine.service);
            parent.refresh();

            // Keeps the parent's own triggerItem in sync with this child's state for its whole
            // lifetime, not just at link time - safe to call unconditionally, see the `isSubmenu`
            // guard in renderTriggerItems().
            this.machine.subscribe(() => parent.refresh());
        });
    }

    render() {
        this.spreadPropsByOptionalValue('trigger', ({ value }) =>
            this.api.getTriggerProps({ value })
        );
        this.spreadPropsByOptionalValue('contextTrigger', ({ value }) =>
            this.api.getContextTriggerProps({ value })
        );

        const indicatorEl = this.getElement('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

        const positionerEl = this.getElement('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.getElement('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.getElement('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.getElement('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        this.spreadPropsByValue('itemGroup', ({ value }) =>
            this.api.getItemGroupProps({ id: value })
        );
        this.spreadPropsByValue('itemGroupLabel', ({ value }) =>
            this.api.getItemGroupLabelProps({ htmlFor: value })
        );

        this.spreadPropsByValue('separator', () => this.api.getSeparatorProps());

        this.spreadPropsByValue('item', ({ value, el }) => {
            if (el.dataset.type) {
                this.renderOptionItem(el);
                return;
            }

            return this.api.getItemProps({
                value,
                disabled: el.hasAttribute('data-disabled'),
                valueText: el.dataset.valuetext,
            });
        });

        this.renderTriggerItems();
    }

    /**
     * Spreads `getTriggerItemProps(childApi)` onto each of this menu's own `menu.triggerItem`
     * elements - each one opens a *different* submenu, discriminated by its `childId` (`data-value`).
     * See {@see triggerItemEls} for why found elements are cached instead of re-discovered by id on
     * every render.
     */
    private renderTriggerItems() {
        this.getElements<HTMLElement>('triggerItem').forEach(el => {
            const childId = el.dataset.value;
            if (childId && !this.triggerItemEls.has(childId)) this.triggerItemEls.set(childId, el);
        });

        this.triggerItemEls.forEach((el, childId) => {
            const child = getComponentInstance<Menu>('menu', childId);
            if (!child) return;

            // Until a child's own isSubmenu flag flips true (its setParent() call resolves
            // asynchronously - see linkToParent()), getTriggerItemProps() computes
            // data-part="trigger" instead of "trigger-item", which collides with that child's own,
            // unrelated menu.trigger rendering and permanently mangles this element's id. Waiting
            // here guarantees data-part is always right from this element's very first spread.
            if (!child.machine.context.get('isSubmenu')) return;

            this.spreadProps(el, this.api.getTriggerItemProps(child.api));
        });
    }

    /**
     * Zag's menu machine doesn't own checked state for checkbox/radio items itself (unlike
     * RadioGroup/CheckboxGroup) - it only calls back `onCheckedChange` with the next value on
     * click. `data-state` is therefore the source of truth across renders: each render reads it,
     * `onCheckedChange` updates it (also unchecking radio siblings sharing the same `name`), then
     * re-renders so the new state is spread onto the DOM again.
     */
    private renderOptionItem(el: HTMLElement) {
        const value = el.dataset.value;
        if (value === undefined) return;

        const type = el.dataset.type === 'radio' ? 'radio' : 'checkbox';
        const disabled = el.hasAttribute('data-disabled');
        const valueText = el.dataset.valuetext;
        const name = el.dataset.name;
        const checked = el.dataset.state === 'checked';

        const optionProps = this.api.getOptionItemProps({
            type,
            value,
            checked,
            disabled,
            valueText,
            onCheckedChange: nextChecked => {
                if (type === 'radio' && name) {
                    this.getElements<HTMLElement>('item').forEach(sibling => {
                        if (sibling.dataset.name === name) {
                            sibling.dataset.state = sibling === el ? 'checked' : 'unchecked';
                        }
                    });
                } else {
                    el.dataset.state = nextChecked ? 'checked' : 'unchecked';
                }
                this.render();
            },
        });
        this.spreadProps(el, optionProps);

        const indicatorEl = this.getElement('itemIndicator', el);
        if (indicatorEl) {
            this.spreadProps(
                indicatorEl,
                this.api.getItemIndicatorProps({ value, checked, disabled, valueText })
            );
        }

        const textEl = this.getElement('itemText', el);
        if (textEl) {
            this.spreadProps(
                textEl,
                this.api.getItemTextProps({ value, checked, disabled, valueText })
            );
        }
    }
}
