import { Component$1 as Component, Machine$1 as Machine } from "../index-AN0XU_8B.js";
import * as _zag_js_types0 from "@zag-js/types";
import * as clipboard from "@zag-js/clipboard";

//#region Resources/Private/Primitives/Clipboard/Clipboard.d.ts
declare class Clipboard extends Component<clipboard.Props, clipboard.Api> {
  name: string;
  initMachine(props: clipboard.Props): Machine<clipboard.Schema>;
  initApi(): clipboard.Api<_zag_js_types0.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { Clipboard };