import { Component$1 as Component, Machine$1 as Machine } from "../index-C51Brj49.js";
import * as accordion from "@zag-js/accordion";

//#region Resources/Private/Primitives/Accordion/Accordion.d.ts
declare class Accordion extends Component<accordion.Props, accordion.Api> {
  name: string;
  initMachine(props: accordion.Props): Machine<any>;
  initApi(): accordion.Api<{
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
export { Accordion };