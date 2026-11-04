import * as toast from '@zag-js/toast';
import { Component, Machine, normalizeProps, Template } from '../../Client';
import { Toast } from '../Toast/Toast';

type ToasterProps = toast.StoreProps & {
    id: string;
    translations?: { groupLabel?: string; closeTriggerLabel?: string };
};

interface MountedToast {
    toast: Toast;
    props: Partial<toast.Props>;
    el: HTMLElement;
}

export class Toaster extends Component<ToasterProps, toast.GroupApi> {
    static componentName = 'toaster';

    /** Creates, updates and dismisses the toasts of this toaster. */
    store!: toast.Store<string>;
    private readonly toasts = new Map<string, MountedToast>();
    private toastTranslations?: toast.IntlTranslations;

    initMachine(props: ToasterProps): Machine<any> {
        const { id, translations, ...storeProps } = props;
        this.store = toast.createStore(storeProps);
        this.toastTranslations = { closeTriggerLabel: translations?.closeTriggerLabel };
        return new Machine(toast.group.machine, { id, store: this.store });
    }

    initApi() {
        return toast.group.connect(this.machine.service, normalizeProps);
    }

    render() {
        const rootEl = this.hydrator.query('root');
        if (!rootEl) return;

        this.spreadProps(
            rootEl,
            this.api.getGroupProps({ label: this.userProps.translations?.groupLabel })
        );

        const ids = new Set<string>();
        const storeToasts = this.api.getToasts();
        // oldest first: the store lists the newest first, and a toast mounted later is the front one
        // TODO: mount in store order once @zag-js/toast keeps the heights in toast order itself (zag#3541)
        [...storeToasts].reverse().forEach((storeProps, reversedIndex) => {
            const index = storeToasts.length - 1 - reversedIndex;
            // the store gives every toast an id
            const id = storeProps.id as string;
            ids.add(id);
            const props = { ...storeProps, index, translations: this.toastTranslations };

            const mounted = this.toasts.get(id);
            if (!mounted) {
                this.toasts.set(id, this.mountToast(rootEl, id, props));
                return;
            }

            // only what changed: every updateProps() call stays in the machine for its whole life
            const changed = Object.fromEntries(
                Object.entries(props).filter(
                    ([key, value]) => mounted.props[key as keyof toast.Props] !== value
                )
            );
            mounted.props = props;
            if (Object.keys(changed).length > 0) mounted.toast.updateProps(changed);
            else mounted.toast.refresh();
        });

        this.toasts.forEach((mounted, id) => {
            if (ids.has(id)) return;
            mounted.toast.destroy();
            mounted.el.remove();
            this.toasts.delete(id);
        });
    }

    /** Creates the `Toast` of one toast, after its element is in the DOM. Override it to use a `Toast` of your own. */
    protected createToast(props: toast.Props): Toast {
        return new Toast(props).init();
    }

    private mountToast(rootEl: HTMLElement, id: string, props: Partial<toast.Props>): MountedToast {
        const template = new Template(this.hydrator, 'toastTemplate');
        const stencilId = template.root.getAttribute('data-toast-root');
        if (!stencilId) {
            throw new Error(
                'Toaster: the root element of the toast template has to be a toast.root.'
            );
        }

        // the parts of the clone carry the id of the template, the toast finds them by its own id
        for (const el of [template.root, ...template.root.querySelectorAll('*')]) {
            for (const attribute of el.attributes) {
                if (attribute.name.startsWith('data-toast-') && attribute.value === stencilId) {
                    attribute.value = id;
                }
            }
        }

        // the newest toast comes first, as in the store
        rootEl.prepend(template);

        return {
            toast: this.createToast({ ...props, parent: this.machine.service } as toast.Props),
            props,
            el: template.root,
        };
    }

    destroy() {
        this.toasts.forEach(({ toast, el }) => {
            toast.destroy();
            el.remove();
        });
        this.toasts.clear();
        super.destroy();
    }
}
