import { ComponentHydrator, Machine, spreadProps } from '.';
import type { ComponentInterface } from '../types';
import type { Attrs } from './spread-props';

export abstract class Component<Props, Api> implements ComponentInterface<Api> {
	document: Document;
	machine: Machine<any>;
	api: Api;
	hydrator: ComponentHydrator | null = null;
	userProps?: Props;
	static name: string;

	get doc(): Document {
		return this.document;
	}

	constructor(props: Props, userDocument: Document = document) {
		this.document = userDocument;
		this.userProps = props;
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

	getName() {
		return (this.constructor as typeof Component).name;
	}

	updateProps(props: Partial<Props>) {
		this.machine.stop();
		const newProps = { ...this.userProps, ...props };
		this.userProps = newProps as Props;
		this.machine = this.initMachine(newProps as Props);
		this.api = this.initApi();
		this.init();
	}

	destroy = () => {
		this.machine.stop();
		this.hydrator?.destroy();
	};

	abstract render(): void;

	spreadProps(node: HTMLElement, attrs: Attrs) {
		spreadProps(node, attrs, this.machine.scope.id);
	}

	getElement<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T | null {
		return this.hydrator?.getElement<T>(part, parent) || null;
	}

	getElements<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T[] {
		return this.hydrator?.getElements<T>(part, parent) || [];
	}

	portalElement(el: HTMLElement | null, target: HTMLElement | Document = this.doc.body): void {
		if (!el) return;
		if (el.parentNode !== target) {
			target.appendChild(el);
			el.setAttribute('data-portalled', 'true');
		}
	}
}
