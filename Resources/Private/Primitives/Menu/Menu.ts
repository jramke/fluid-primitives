import { createFilter } from '@zag-js/i18n-utils';
import * as menu from '@zag-js/menu';
import {
    Component,
    getGlobal,
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

    /** The items a filter took out of the DOM, each with the comment that marks where it belongs. */
    private filteredOut = new Map<HTMLElement, Comment>();

    transformProps(props: menu.Props): menu.Props {
        return {
            ...props,
            onOpenChange: details => {
                if (!details.open) this.resetFilter();
                props.onOpenChange?.(details);
            },
        };
    }

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
        this.hydrator
            .query<HTMLInputElement>('input')
            ?.addEventListener('input', event =>
                this.filterItems((event.target as HTMLInputElement).value)
            );
        return this;
    }

    /**
     * Shows the items whose text contains the query, ignoring case and accents, and takes the others
     * out of the DOM, because the keyboard navigation of the machine reads the items from there. An
     * empty query brings all of them back. Groups without items and the `empty` part follow.
     *
     * @param query - The text to look for in the `valueText` of each item, or in its own text.
     */
    filterItems(query: string) {
        const text = query.trim();
        const filter = createFilter({ sensitivity: 'base', locale: getGlobal('locale') });
        let visible = 0;

        for (const el of [...this.hydrator.queryAll('item'), ...this.filteredOut.keys()]) {
            const matches =
                !text || filter.contains(el.dataset.valuetext ?? el.textContent ?? '', text);
            const marker = this.filteredOut.get(el);

            if (matches) {
                visible++;
                if (marker) {
                    marker.replaceWith(el);
                    this.filteredOut.delete(el);
                }
            } else if (!marker) {
                const placeholder = this.doc.createComment('');
                el.replaceWith(placeholder);
                this.filteredOut.set(el, placeholder);
            }
        }

        this.hydrator.queryAll('itemGroup').forEach(group => {
            group.hidden = this.hydrator.queryAll('item', group).length === 0;
        });
        this.hydrator.queryAll('separator').forEach(separator => {
            separator.hidden = text !== '';
        });
        for (const part of ['content', 'list'] as const) {
            this.hydrator.query(part)?.toggleAttribute('data-empty', visible === 0);
        }
        const emptyEl = this.hydrator.query('empty');
        if (emptyEl) emptyEl.hidden = visible > 0;

        this.render();
    }

    private resetFilter() {
        const inputEl = this.hydrator.query<HTMLInputElement>('input');
        if (!inputEl) return;
        inputEl.value = '';
        this.filterItems('');
    }

    // Not a machine prop (`splitProps` drops it), so it's read from the props the instance was created with.
    private get parentId(): string | undefined {
        return (this.userProps as menu.Props & { parentId?: string }).parentId;
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

        const indicatorEl = this.hydrator.query('indicator');
        if (indicatorEl) this.spreadProps(indicatorEl, this.api.getIndicatorProps());

        const positionerEl = this.hydrator.query('positioner');
        if (positionerEl) this.spreadProps(positionerEl, this.api.getPositionerProps());

        const arrowEl = this.hydrator.query('arrow');
        if (arrowEl) this.spreadProps(arrowEl, this.api.getArrowProps());

        const arrowTipEl = this.hydrator.query('arrowTip');
        if (arrowTipEl) this.spreadProps(arrowTipEl, this.api.getArrowTipProps());

        const contentEl = this.hydrator.query('content');
        if (contentEl) this.spreadProps(contentEl, this.api.getContentProps());

        const inputEl = this.hydrator.query('input');
        if (inputEl) this.spreadProps(inputEl, this.api.getInputProps());

        const listEl = this.hydrator.query('list');
        if (listEl) this.spreadProps(listEl, this.api.getListProps());

        const emptyEl = this.hydrator.query('empty');
        if (emptyEl) this.spreadProps(emptyEl, this.api.getEmptyProps());

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

            const el = child.hydrator.query('triggerItem');
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
                    this.hydrator.queryAll<HTMLElement>('item').forEach(sibling => {
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

        const indicatorEl = this.hydrator.query('itemIndicator', el);
        if (indicatorEl) {
            this.spreadProps(
                indicatorEl,
                this.api.getItemIndicatorProps({ value, checked, disabled, valueText })
            );
        }

        const textEl = this.hydrator.query('itemText', el);
        if (textEl) {
            this.spreadProps(
                textEl,
                this.api.getItemTextProps({ value, checked, disabled, valueText })
            );
        }
    }
}
