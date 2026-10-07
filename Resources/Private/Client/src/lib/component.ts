import type { Attrs } from '@zag-js/vanilla';
import {
    ComponentHydrator,
    getComponentInstance,
    Machine,
    spreadProps,
    spreadTextControlProps,
    toKebabCase,
} from '.';
import type { ComponentInterface } from '../types';

export abstract class Component<Props, Api> implements ComponentInterface<Api> {
    document: Document;
    #machine?: Machine<any>;
    #api?: Api;
    #hydrator?: ComponentHydrator;
    #userProps?: Props;
    /**
     * The Fluid namespace identifier (e.g. "ui") this instance was mounted under - assigned by
     * `mountAll`/`mount` right after their callback returns, so not yet available while it runs
     * (`init()` included).
     */
    namespace?: string;
    static componentName: string;

    get doc(): Document {
        return this.document;
    }

    constructor(
        private readonly initialProps: Props,
        userDocument: Document = document
    ) {
        this.document = userDocument;
    }

    /** Created by {@see init} - reading it earlier throws. */
    get machine(): Machine<any> {
        return this.#requireInit(this.#machine, 'machine');
    }

    /** Created by {@see init} - reading it earlier throws. */
    get api(): Api {
        return this.#requireInit(this.#api, 'api');
    }

    protected set api(api: Api) {
        this.#api = api;
    }

    /**
     * Finds the component's parts by name, see {@see ComponentHydrator.query}. Created by
     * {@see init} - reading it earlier throws.
     */
    get hydrator(): ComponentHydrator {
        return this.#requireInit(this.#hydrator, 'hydrator');
    }

    /**
     * The props the instance runs on - what {@see transformProps} made of the ones it was created
     * with. Created by {@see init} - reading it earlier throws.
     */
    get userProps(): Props {
        return this.#requireInit(this.#userProps, 'userProps');
    }

    #requireInit<T>(value: T | undefined, member: string): T {
        if (value === undefined) {
            throw new Error(
                `${this.getName()}: \`${member}\` was accessed before init(). It only exists once init() has run - call init() first.`
            );
        }
        return value;
    }

    abstract initMachine(props: Props): Machine<any>;
    abstract initApi(): Api;

    initHydrator(props: Props) {
        const id = (props as any).id;
        if (!id) throw new Error('ComponentHydrator requires an id prop to initialize.');
        return new ComponentHydrator(this.getName(), id, this.doc);
    }

    /**
     * Builds everything the instance runs on - `userProps`, hydrator, machine and api - then renders
     * once and starts the machine.
     *
     * None of it happens in the constructor on purpose: `transformProps`, `initMachine` and
     * `initApi` are overridden by subclasses, and a subclass' own field initializers only run after
     * `super()` returns. A hook called from the base constructor would see those fields unset, and
     * whatever it assigned to one would be reset right afterwards.
     */
    init() {
        this.#userProps = this.transformProps(this.initialProps);
        this.#hydrator = this.initHydrator(this.#userProps);
        this.#machine = this.initMachine(this.#userProps);
        this.#api = this.initApi();

        this.render();
        this.#machine.subscribe(() => {
            this.#api = this.initApi();
            this.render();
        });
        this.#machine.start();
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
     * Override to adjust the props before anything else sees them, e.g. to wrap a callback prop.
     * Runs once, first in {@see init}: the result becomes `userProps`, which `initHydrator()` and
     * `initMachine()` receive.
     */
    transformProps(props: Props): Props {
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
        this.hydrator.destroy();
    }

    spreadProps(node: HTMLElement, attrs: Attrs) {
        spreadProps(node, attrs, this.machine.scope.id);
    }

    /** {@see spreadTextControlProps}: for a text control that mirrors what the user typed. */
    spreadTextControlProps(node: HTMLInputElement | HTMLTextAreaElement, attrs: Attrs) {
        spreadTextControlProps(node, attrs, this.machine.scope.id);
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
        this.hydrator.queryAll<HTMLElement>(part, options?.parent).forEach(el => {
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
        this.hydrator.queryAll<HTMLElement>(part, options?.parent).forEach(el => {
            const value = el.dataset.value;
            const props = getProps({ el, value });
            if (props) this.spreadProps(el, props);
        });
    }

    abstract render(): void;
}
