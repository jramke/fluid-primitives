import type { Attrs } from '@zag-js/vanilla';
import { ComponentHydrator, Machine, spreadProps } from '.';
import type { ComponentInterface } from '../types';

export abstract class Component<Props, Api> implements ComponentInterface<Api> {
    document: Document;
    machine: Machine<any>;
    api: Api;
    hydrator: ComponentHydrator | null = null;
    userProps?: Partial<Props>;
    static name: string;

    get doc(): Document {
        return this.document;
    }

    constructor(props: Props, userDocument: Document = document) {
        this.document = userDocument;
        this.userProps = this.transformProps(props);
        // TODO: should we pass the transformed props to initHydrator and initMachine? Or should we pass the original props?
        this.hydrator = this.initHydrator(props);
        this.machine = this.initMachine(props);
        this.api = this.initApi();
    }

    abstract initMachine(props: Props): Machine<any>;
    abstract initApi(): Api;

    initHydrator(props: Props) {
        const id = (props as any).id;
        if (!id) throw new Error('ComponentHydrator requires an id prop to initialize.');
        return new ComponentHydrator(this.getName(), id, (props as any).ids, this.doc);
    }

    init() {
        this.render();
        this.machine.subscribe(() => {
            this.api = this.initApi();
            this.render();
        });
        this.machine.start();
    }

    /**
     * Forces a fresh render() pass with no prop change behind it - use after mutating the DOM
     * directly (e.g. inserting a new `Template` instance) for a primitive with no prop that
     * naturally triggers a re-render on its own (unlike Combobox/Select, where updateProps({
     * collection }) already causes one). Reaches past `machine.notify`'s type-only privacy the
     * same way FieldAwareComponent's own field-sync logic already does internally.
     */
    refresh(): void {
        // notify is marked as private but that does not prevent runtime access
        // @ts-expect-error
        this.machine.notify();
    }

    getName() {
        return (this.constructor as typeof Component).name;
    }

    /**
     * Override in consumer for example when a getter is used for collection
     * Needs to be used manually inside the initMachine method
     */
    transformProps(props: Partial<Props>): Partial<Props> {
        return props;
    }

    updateProps(newProps: Partial<Props>) {
        this.machine.updateProps(newProps);
    }

    destroy() {
        this.machine.stop();
        this.hydrator?.destroy();
    }

    spreadProps(node: HTMLElement, attrs: Attrs) {
        spreadProps(node, attrs, this.machine.scope.id);
    }

    getElement<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T | null {
        return this.hydrator?.getElement<T>(part, parent) || null;
    }

    getElements<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T[] {
        return this.hydrator?.getElements<T>(part, parent) || [];
    }

    /**
     * For every element matching `part`, reads its own `data-value` and hands `{el, value}` to
     * `getProps`; if it returns a props object, spreads it onto that element via `spreadProps`.
     * Returning `null`/`undefined` skips the element (e.g. a value that no longer resolves
     * against a collection). Deliberately does not read any other attribute
     * (disabled/invalid/current/...) or resolve a collection itself - callers read whatever flags
     * they need off `el` and do their own value resolution, since both vary per primitive.
     */
    protected spreadPropsByValue(
        part: string,
        getProps: (ctx: { el: HTMLElement; value: string }) => Attrs | null | undefined,
        parent?: HTMLElement | Document
    ): void {
        this.getElements<HTMLElement>(part, parent).forEach(el => {
            const value = el.dataset.value;
            if (value === undefined) return;
            const props = getProps({ el, value });
            if (props) this.spreadProps(el, props);
        });
    }

    abstract render(): void;
}
