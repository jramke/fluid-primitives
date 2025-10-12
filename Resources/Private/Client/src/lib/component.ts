import { ComponentHydrator, Machine, spreadProps } from '.';
import type { ComponentInterface } from '../types';
import type { Attrs } from './spread-props';

export abstract class Component<Props, Api> implements ComponentInterface<Api> {
	document: Document;
	machine: Machine<any>;
	api: Api;
	hydrator: ComponentHydrator | null = null;
	userProps?: Props;
	abstract readonly name: string;

	get doc(): Document {
		return this.document;
	}

	constructor(props: Props, userDocument: Document = document) {
		this.document = userDocument;
		this.userProps = props;
		this.machine = this.initMachine(props);
		this.api = this.initApi();
	}

	abstract initMachine(props: Props): Machine<any>;
	abstract initApi(): Api;

	init() {
		this.hydrator = new ComponentHydrator(
			this.name,
			this.machine.scope.id,
			this.machine.scope.ids,
			this.doc
		);
		this.render();
		this.machine.subscribe(() => {
			this.api = this.initApi();
			this.render();
		});
		this.machine.start();
	}

	updateProps(props: Props) {
		this.machine.stop();
		this.machine = this.initMachine({ ...this.userProps, ...props });
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
