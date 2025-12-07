import { FieldAwareComponent$1 as FieldAwareComponent, FieldMachine, Machine$1 as Machine } from "./index-D3h8ShLC.js";
import * as checkbox from "@zag-js/checkbox";

//#region Resources/Private/Primitives/Checkbox/Checkbox.d.ts
declare class Checkbox extends FieldAwareComponent<checkbox.Props, checkbox.Api> {
  static name: string;
  propsWithField(props: checkbox.Props, fieldMachine: FieldMachine): checkbox.Props;
  initMachine(props: checkbox.Props): Machine<any>;
  initApi(): checkbox.Api<{
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
export { Checkbox };