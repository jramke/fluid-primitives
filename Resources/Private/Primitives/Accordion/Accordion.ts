import * as accordion from '@zag-js/accordion';
import * as collapsible from '@zag-js/collapsible';
import { Component, Machine, normalizeProps, spreadProps, uid } from '../../Client';

// what the collapsible needs from the accordion. `disabled` only stops its `beforematch` handler, the
// accordion renders `data-disabled` and `dir` on the content itself
type ItemCollapsibleProps = Pick<collapsible.Props, 'open' | 'disabled' | 'hiddenUntilFound'>;
type ItemCollapsible = { machine: Machine<any>; props: ItemCollapsibleProps };

export class Accordion extends Component<accordion.Props, accordion.Api> {
    static componentName = 'accordion';
    // one collapsible per item content, as in Ark: it owns the visibility, measures `--height` and
    // keeps a closing item visible until its exit animation ends
    #collapsibles = new Map<HTMLElement, ItemCollapsible>();

    initMachine(props: accordion.Props): Machine<any> {
        const [machineProps] = accordion.splitProps(props);
        return new Machine(accordion.machine, machineProps);
    }

    initApi() {
        return accordion.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (rootEl) {
            this.spreadProps(rootEl, this.api.getRootProps());
        }

        this.spreadPropsByValue('item', ({ el, value }) =>
            this.api.getItemProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('itemTrigger', ({ el, value }) =>
            this.api.getItemTriggerProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('itemContent', ({ el, value }) => {
            const item = { value, disabled: el.hasAttribute('data-disabled') };
            const { hidden, 'data-state': state, ...props } = this.api.getItemContentProps(item);
            this.#syncCollapsible(el, this.api.getItemState(item));
            return props;
        });

        this.spreadPropsByValue('itemIndicator', ({ el, value }) =>
            this.api.getItemIndicatorProps({ value, disabled: el.hasAttribute('data-disabled') })
        );

        this.spreadPropsByValue('itemHeader', ({ el, value }) =>
            this.api.getItemHeaderProps({ value, disabled: el.hasAttribute('data-disabled') })
        );
    }

    #syncCollapsible(el: HTMLElement, itemState: accordion.ItemState) {
        // read live, so updateProps() on the accordion reaches the collapsibles too
        const props: ItemCollapsibleProps = {
            open: itemState.expanded,
            disabled: itemState.disabled,
            hiddenUntilFound: this.machine.prop('hiddenUntilFound'),
        };
        const existing = this.#collapsibles.get(el);
        if (existing) {
            // updateProps() always notifies, so only call it on a change
            const keys = Object.keys(props) as (keyof ItemCollapsibleProps)[];
            if (keys.every(key => existing.props[key] === props[key])) return;
            existing.props = props;
            existing.machine.updateProps(props);
            return;
        }

        const machine = new Machine(collapsible.machine, {
            id: uid('accordion-collapsible'),
            ...props,
        });
        const renderContent = () => {
            // the accordion owns the rest of the content's props
            const {
                id,
                dir,
                'data-disabled': disabled,
                ...props
            } = collapsible.connect(machine.service, normalizeProps).getContentProps();
            spreadProps(el, props, machine.scope.id);
        };
        this.#collapsibles.set(el, { machine, props });
        renderContent();
        machine.subscribe(renderContent);
        machine.start();
        // Zag measures only when the state changes, so content open on load would close from a
        // `--height` of 0
        if (props.open) machine.send({ type: 'size.measure' });
    }

    destroy() {
        this.#collapsibles.forEach(({ machine }) => machine.stop());
        this.#collapsibles.clear();
        super.destroy();
    }
}
