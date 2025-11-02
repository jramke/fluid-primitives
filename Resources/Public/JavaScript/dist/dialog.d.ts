import { Component$1 as Component, Machine$1 as Machine } from "./index-ir2YxHr8.js";
import * as _zag_js_types0 from "@zag-js/types";
import * as dialog from "@zag-js/dialog";

//#region Resources/Private/Primitives/Dialog/Dialog.d.ts
declare class Dialog extends Component<dialog.Props, dialog.Api> {
  name: string;
  initMachine(props: dialog.Props): Machine<any>;
  initApi(): dialog.Api<_zag_js_types0.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Dialog };