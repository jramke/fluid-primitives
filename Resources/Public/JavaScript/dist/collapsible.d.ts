import { Component$1 as Component, Machine$1 as Machine } from "./index-D3h8ShLC.js";
import * as _zag_js_types2 from "@zag-js/types";
import * as collapsible from "@zag-js/collapsible";

//#region Resources/Private/Primitives/Collapsible/Collapsible.d.ts
declare class Collapsible extends Component<collapsible.Props, collapsible.Api> {
  static name: string;
  initMachine(props: collapsible.Props): Machine<any>;
  initApi(): collapsible.Api<_zag_js_types2.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Collapsible };