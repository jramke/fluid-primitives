import type { Attrs } from '@zag-js/vanilla';
import { ComponentHydrator, getComponentInstance, Machine, spreadProps, toKebabCase } from '.';
import type { ComponentInterface } from '../types';

export abstract class Component<Props, Api> implements ComponentInterface<Api> {
    document: Document;
    machine: Machine<any>;
    api: Api;
    hydrator: ComponentHydrator | null = null;
    userProps?: Partial<Props>;
    /**
     * The Fluid namespace identifier (e.g. "ui") this instance was mounted under - assigned by
     * `mountAll`/`mount` right after their callback returns, so not yet available while it runs
     * (constructor, `init()`).
     */
    namespace?: string;
    static componentName: string;

    get doc(): Document {
        return this.document;
    }

    constructor(props: Props, userDocument: Document = document) {
        this.document = userDocument;
        this.userProps = this.transformProps(props);
        // Deliberately the original props, not `userProps` - subclasses that override
        // transformProps() call it themselves inside initMachine(), after their own
        // field/group merging and prop filtering. Passing the already-transformed
        // `userProps` here would run transformProps() twice, double-wrapping callback
        // props like Combobox/NumberInput's onSelect/onValueChange.
        this.hydrator = this.initHydrator(props);
        this.machine = this.initMachine(props);
        this.api = this.initApi();
    }

    abstract initMachine(props: Props): Machine<any>;
    abstract initApi(): Api;

    initHydrator(props: Props) {
        const id = (props as any).id;
        if (!id) throw new Error('ComponentHydrator requires an id prop to initialize.');
        return new ComponentHydrator(this.getName(), id, this.doc);
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
        return (this.constructor as typeof Component).componentName;
    }

    /**
     * Kebab form of {@see getName} - what actually appears in DOM-facing identifiers (part attribute
     * names, hydration keys). See {@see ComponentHydrator.clientComponentName}.
     */
    getClientName() {
        return toKebabCase(this.getName());
    }

    /**
     * Override in consumer for example when a getter is used for collection
     * Needs to be used manually inside the initMachine method
     */
    transformProps(props: Partial<Props>): Partial<Props> {
        return props;
    }

    /**
     * Another already-mounted instance of this same component, by root id - for a primitive that
     * composes two independent instances of itself (Menu submenus). Only resolves once
     * `mountAll`/`mount` assigned {@see namespace}.
     */
    protected getPeerInstance<T extends Component<any, any>>(rootId: string): T | undefined {
        return this.namespace
            ? getComponentInstance<T>(`${this.namespace}:${this.getName()}`, rootId)
            : undefined;
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

    query<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T | null {
        return this.hydrator?.query<T>(part, parent) ?? null;
    }

    queryAll<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T[] {
        return this.hydrator?.queryAll<T>(part, parent) ?? [];
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
        options?: { parent?: HTMLElement | Document }
    ): void {
        this.queryAll<HTMLElement>(part, options?.parent).forEach(el => {
            const value = el.dataset.value;
            if (value === undefined) return;
            const props = getProps({ el, value });
            if (props) this.spreadProps(el, props);
        });
    }

    protected spreadPropsByOptionalValue(
        part: string,
        getProps: (ctx: { el: HTMLElement; value?: string }) => Attrs | null | undefined,
        options?: { parent?: HTMLElement | Document }
    ): void {
        this.queryAll<HTMLElement>(part, options?.parent).forEach(el => {
            const value = el.dataset.value;
            const props = getProps({ el, value });
            if (props) this.spreadProps(el, props);
        });
    }

    abstract render(): void;
}
