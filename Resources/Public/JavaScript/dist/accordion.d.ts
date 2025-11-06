import { Component$1 as Component, Machine$1 as Machine } from "./index-BxBl3DfU.js";
import * as _zag_js_types0 from "@zag-js/types";
import * as accordion from "@zag-js/accordion";

//#region Resources/Private/Primitives/Accordion/Accordion.d.ts
declare class Accordion extends Component<accordion.Props, accordion.Api> {
  name: string;
  initMachine(props: accordion.Props): Machine<any>;
  initApi(): accordion.Api<_zag_js_types0.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Accordion };