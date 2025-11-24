import { Bindable, BindableContext, BindableRefs, ComputedFn, Machine, MachineSchema, Params, PropFn, Scope, Service, mergeProps } from "@zag-js/core";
import { ListCollection } from "@zag-js/collection";
import * as _zag_js_types7 from "@zag-js/types";

//#region Resources/Private/Client/src/types.d.ts
declare global {
  interface Window {
    FluidPrimitives: {
      hydrationData: {
        [componentName: string]: {
          [id: string]: ComponentHydrationData;
        };
      };
      uncontrolledInstances: {
        [componentName: string]: {
          [id: string]: Component<unknown, unknown>;
        };
      };
    };
  }
}
interface ComponentInterface<Api> {
  document: Document;
  machine: Machine$1<any>;
  api: Api;
  hydrator: ComponentHydrator | null;
  init(): void;
  destroy(): void;
  render(): void;
}
interface ComponentHydrationData {
  controlled: boolean;
  props: {
    id: string;
    ids: {
      [key: string]: string;
    };
    [key: string]: unknown;
  };
}
//#endregion
//#region Resources/Private/Client/src/lib/spread-props.d.ts
interface Attrs {
  [key: string]: any;
}
declare function spreadProps(node: Element, attrs: Attrs, machineId?: string): () => void;
//#endregion
//#region Resources/Private/Client/src/lib/component.d.ts
declare abstract class Component<Props, Api> implements ComponentInterface<Api> {
  document: Document;
  machine: Machine$1<any>;
  api: Api;
  hydrator: ComponentHydrator | null;
  userProps?: Props;
  abstract readonly name: string;
  get doc(): Document;
  constructor(props: Props, userDocument?: Document);
  abstract initMachine(props: Props): Machine$1<any>;
  abstract initApi(): Api;
  init(): void;
  updateProps(props: Props): void;
  destroy: () => void;
  abstract render(): void;
  spreadProps(node: HTMLElement, attrs: Attrs): void;
  getElement<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T | null;
  getElements<T extends HTMLElement>(part: string, parent?: HTMLElement | Document): T[];
  portalElement(el: HTMLElement | null, target?: HTMLElement | Document): void;
}
//#endregion
//#region Resources/Private/Client/src/lib/hydration.d.ts
declare function getHydrationData(component: string): Record<string, ComponentHydrationData> | null;
declare function getHydrationData(component: string, id: string): ComponentHydrationData | null;
declare function initAllComponentInstances(componentName: string, callback: (data: ComponentHydrationData) => Component<unknown, unknown> | void): void;
declare class ComponentHydrator {
  componentName: string;
  doc: Document;
  rootId: string;
  ids: {
    [key: string]: string;
  };
  elementRefs: Map<string, Element | Element[]>;
  constructor(componentName: string, rootId: string | undefined, ids?: {
    [key: string]: string;
  }, doc?: Document);
  getElement<T extends Element>(part: string, parent?: Element | Document): T | null;
  getElements<T extends Element>(part: string, parent?: Element | Document): T[];
  generateRefAttributesString(part: string): string;
  setRefAttributes(element: Element, part: string): void;
  destroy(): void;
}
//#endregion
//#region Resources/Private/Client/src/lib/machine.d.ts
declare class Machine$1<T extends MachineSchema> {
  private machine;
  scope: Scope;
  ctx: BindableContext<T>;
  prop: PropFn<T>;
  state: Bindable<T['state']>;
  refs: BindableRefs<T>;
  computed: ComputedFn<T>;
  private event;
  private previousEvent;
  private effects;
  private transition;
  private cleanups;
  private subscriptions;
  private getEvent;
  private getState;
  debug: (...args: any[]) => void;
  notify: () => void;
  constructor(machine: Machine<T>, userProps?: Partial<T['props']> | (() => Partial<T['props']>));
  send: (event: any) => void;
  private action;
  private guard;
  private effect;
  private choose;
  start(): void;
  stop(): void;
  subscribe: (fn: (service: Service<T>) => void) => void;
  private status;
  get service(): Service<T>;
  private publish;
  private trackers;
  private setupTrackers;
  private callTrackers;
  getParams: () => Params<T>;
}
//#endregion
//#region Resources/Private/Client/src/lib/normalize-props.d.ts
declare const normalizeProps: _zag_js_types7.NormalizeProps<_zag_js_types7.PropTypes<{
  [x: string]: any;
}>>;
//#endregion
//#region Resources/Private/Client/src/lib/uid.d.ts
declare function uid(prefix?: string): string;
//#endregion
export { Component as Component$1, ComponentHydrationData, ComponentHydrator as ComponentHydrator$1, ComponentInterface, Machine$1, getHydrationData as getHydrationData$1, initAllComponentInstances as initAllComponentInstances$1, mergeProps as mergeProps$1, normalizeProps as normalizeProps$1, spreadProps as spreadProps$1, uid as uid$1 };