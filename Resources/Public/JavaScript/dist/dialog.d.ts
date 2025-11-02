import { Component$1 as Component, Machine$1 as Machine } from "./index-BQR4r1rn.js";
import * as dialog from "@zag-js/dialog";

//#region Resources/Private/Primitives/Dialog/Dialog.d.ts
declare class Dialog extends Component<dialog.Props, dialog.Api> {
  name: string;
  initMachine(props: dialog.Props): Machine<any>;
  initApi(): dialog.Api<{
    button: {
      [x: string]: any;
    };
    label: {
      [x: string]: any;
    };
    input: {
      [x: string]: any;
    };
    textarea: {
      [x: string]: any;
    };
    img: {
      [x: string]: any;
    };
    output: {
      [x: string]: any;
    };
    element: {
      [x: string]: any;
    };
    select: {
      [x: string]: any;
    };
    rect: {
      [x: string]: any;
    };
    style: {
      [x: string]: any;
    };
    circle: {
      [x: string]: any;
    };
    svg: {
      [x: string]: any;
    };
    path: {
      [x: string]: any;
    };
  }>;
  render(): void;
}
//#endregion
export { Dialog };