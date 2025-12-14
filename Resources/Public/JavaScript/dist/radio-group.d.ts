import { Component$1 as Component, Machine$1 as Machine } from "./index-6fHSGVtd.js";
import * as _zag_js_types7 from "@zag-js/types";
import * as radioGroup from "@zag-js/radio-group";

//#region Resources/Private/Primitives/RadioGroup/RadioGroup.d.ts
declare class RadioGroup extends Component<radioGroup.Props, radioGroup.Api> {
  static name: string;
  initMachine(props: radioGroup.Props): Machine<any>;
  initApi(): radioGroup.Api<_zag_js_types7.PropTypes<{
    [x: string]: any;
  }>>;
  render(): void;
}
//#endregion
export { RadioGroup };