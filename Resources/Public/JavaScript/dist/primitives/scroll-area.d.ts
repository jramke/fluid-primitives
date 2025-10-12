import { Component$1 as Component, Machine$1 as Machine } from "../index-fBbeAkfp.js";
import { PropTypes } from "@zag-js/types";
import * as scrollArea from "@zag-js/scroll-area";

//#region Resources/Private/Primitives/ScrollArea/ScrollArea.d.ts
declare class ScrollArea extends Component<scrollArea.Props, scrollArea.Api<PropTypes>> {
  name: string;
  initMachine(props: scrollArea.Props): Machine<any>;
  initApi(): scrollArea.Api<{
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
export { ScrollArea };