import * as menu from '@zag-js/menu';
import {
    Component,
    Machine,
    normalizeProps,
    registerClientPropConverters,
    type ClientPropConverterMap,
    type ConverterMachineProps,
} from '../../Client';

// PHP can't distinguish a list-shaped array from an object-shaped one for a bare `type="array"`
// prop (see WireTypeResolver), so `positioning` resolves to `unknown` on the wire - this converter
// just tells TS what it actually is (a real @zag-js/popper PositioningOptions object) rather than
// transforming the value itself.
const menuPropConverters = {
    positioning: (positioning: menu.Props['positioning']) => positioning,
} satisfies ClientPropConverterMap;

registerClientPropConverters('menu', menuPropConverters);

declare module 'fluid-primitives' {
    interface HydrationPropsOverrides {
        menu: ConverterMachineProps<typeof menuPropConverters>;
    }
}

export class Menu extends Component<menu.Props, menu.Api> {
    static componentName = 'menu';

    /**
     * Submenus that linked themselves to this menu (see {@see linkToParent}). Zag composes a
     * `menu.triggerItem` from this menu's item props and the *child's* trigger props, and marks it
     * with the child's own scope id - so each one is found through its child, not through this menu.
     */
    private submenus = new Set<Menu>();

    initMachine(props: menu.Props): Machine<any> {
        const [menuProps] = menu.splitProps(props);
        return new Machine(menu.machine, menuProps);
    }

    initApi() {
        return menu.connect(this.machine.service, normalizeProps);
    }

    init() {
        super.init();
        this.linkToParent();
    }

    // Not a machine prop (`splitProps` drops it), so it's read from the props the instance was created with.
    private get parentId(): string | undefined {
        return (this.userProps as { parentId?: string } | undefined)?.parentId;
    }

    private getParentInstance(): Menu | null {
        return this.parentId ? (this.getPeerInstance<Menu>(this.parentId) ?? null) : null;
    }

    /**
     * Composes two independent `Menu` instances into a submenu relationship - see `menu.root`'s
     * `parentId` prop and `menu.triggerItem`'s `childId` prop. Zag's own `setParent`/`setChild` API
     * expects live `MenuService` instances, which only exist once both components have mounted, so
     * this runs after `init()` (deferred via `setTimeout`, so it doesn't depend on mount order
     * between the two) rather than as a machine prop.
     */
    private linkToParent() {
        if (!this.parentId) return;

        setTimeout(() => {
            const parent = this.getParentInstance();
            if (!parent) return;

            parent.submenus.add(this);
            parent.api.setChild(this.machine.service);
            this.api.setParent(parent.machine.service);
            parent.refresh();

            // Keeps the parent's own triggerItem in sync with this child's state for its whole
            // lifetime, not just at link time - safe to call unconditionally, see the `isSubmenu`
            // guard in renderTriggerItems().
            this.machine.subscribe(() => parent.refresh());
        });
    }

    destroy() {
        this.getParentInstance()?.submenus.delete(this);
        super.destroy();
    }

    render() {
        this.spreadPropsByOptionalValue('trigger', ({ value }) =>
            this.api.getTriggerProps({ value })
        );
        this.spreadPropsByOptionalValue('contextTrigger', ({ value }) =>
            this.api.getContextTriggerProps({ value })
        );

        const indicatorEl = this.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

        const positionerEl = this.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.query('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.query('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        this.spreadPropsByValue('itemGroup', ({ value }) =>
            this.api.getItemGroupProps({ id: value })
        );
        this.spreadPropsByValue('itemGroupLabel', ({ value }) =>
            this.api.getItemGroupLabelProps({ htmlFor: value })
        );

        this.spreadPropsByOptionalValue('separator', () => this.api.getSeparatorProps());

        this.spreadPropsByValue('item', ({ value, el }) => {
            // A submenu's trigger item also carries this menu's `data-menu-item` once linked (zag
            // merges this menu's item props into it) - it's spread in renderTriggerItems(), and
            // spreading plain item props over it here would strip what that spread added.
            if (el.hasAttribute('data-menu-trigger-item')) return;

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
     * Spreads `getTriggerItemProps(childApi)` onto the `menu.triggerItem` element of each linked
     * submenu - it carries that submenu's own root id, so the child finds it with its own
     * `query('triggerItem')`.
     */
    private renderTriggerItems() {
        this.submenus.forEach(child => {
            // Until a child's own isSubmenu flag flips true (its setParent() call resolves
            // asynchronously - see linkToParent()), getTriggerItemProps() computes
            // data-menu-trigger instead of data-menu-trigger-item, which collides with that child's
            // own, unrelated menu.trigger rendering and permanently mangles this element's id.
            // Waiting here guarantees the part attribute is always right from this element's very
            // first spread.
            if (!child.machine.context.get('isSubmenu')) return;

            const el = child.query('triggerItem');
            if (el) this.spreadProps(el, this.api.getTriggerItemProps(child.api));
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
                    this.queryAll<HTMLElement>('item').forEach(sibling => {
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

        const indicatorEl = this.query('itemIndicator', el);
        if (indicatorEl) {
            this.spreadProps(
                indicatorEl,
                this.api.getItemIndicatorProps({ value, checked, disabled, valueText })
            );
        }

        const textEl = this.query('itemText', el);
        if (textEl) {
            this.spreadProps(
                textEl,
                this.api.getItemTextProps({ value, checked, disabled, valueText })
            );
        }
    }
}
