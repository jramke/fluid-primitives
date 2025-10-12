import { Component$1 as Component, Machine$1 as Machine } from "../index-fBbeAkfp.js";
import * as clipboard from "@zag-js/clipboard";

//#region Resources/Private/Primitives/Clipboard/Clipboard.d.ts
declare class Clipboard extends Component<clipboard.Props, clipboard.Api> {
  name: string;
  initMachine(props: clipboard.Props): Machine<clipboard.Schema>;
  initApi(): clipboard.Api<{
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
export { Clipboard };