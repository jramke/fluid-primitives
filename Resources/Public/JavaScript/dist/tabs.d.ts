import { Component$1 as Component, Machine$1 as Machine } from "./index-iJSgk-K3.js";
import * as _zag_js_types4 from "@zag-js/types";
import * as tabs from "@zag-js/tabs";

//#region Resources/Private/Primitives/Tabs/Tabs.d.ts
declare class Tabs extends Component<tabs.Props, tabs.Api> {
  static name: string;
  initMachine(props: tabs.Props): Machine<any>;
  initApi(): tabs.Api<_zag_js_types4.PropTypes<{
    [x: string]: any;
  }>>;
  render: () => void;
}
//#endregion
export { Tabs };