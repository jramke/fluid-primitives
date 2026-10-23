# Changelog

What changed in each release of Fluid Primitives, newest first. Breaking changes come first in every release. The library follows [semantic versioning](https://semver.org), and the 1.0 betas may still change between releases.

## Unreleased

**Fixes**

- A `ListCollection` item with a number as its value or label is found by the client. Quoted values like `'1'` in an inline array no longer miss their item in `Select` and `Combobox`. The items no longer render a `data-item` attribute. ([`28e1504`](https://github.com/jramke/fluid-primitives/commit/28e1504))
- `asChild` keeps attribute values of the child element that contain `$` or `\`, like `title="Cost $10"`. A value such as `$0 onmouseover=...` no longer breaks out of its attribute and adds an event handler. ([#86](https://github.com/jramke/fluid-primitives/pull/86))
- An attribute you forward with `attributes="{ui:attributes(asArray: true)}"` or through `spreadProps` is escaped once. `Tom & "Jerry"` no longer arrives as `Tom &amp; &quot;Jerry&quot;`. ([#86](https://github.com/jramke/fluid-primitives/pull/86))
- The item text and item indicator of a `Select` render inside a `ui:template`, so a stencil can hold an item the client clones, as in `Combobox`. ([`f4285d4`](https://github.com/jramke/fluid-primitives/commit/f4285d4))

## 1.0.0-beta.4

_2026-10-23_

**Breaking changes**

- Forms send through `extbase.post`. The `onSubmit` callback of a form no longer receives a `post` helper, call `extbase.post(...)` instead. The form element no longer renders `data-field-name-prefix` and `data-object-name`. ([#77](https://github.com/jramke/fluid-primitives/pull/77))
- `extbase.get()` and `extbase.post()` reject with an `ExtbaseValidationError` when the server answers with a JSON `422`. Every other status still resolves as a `Response`. ([#77](https://github.com/jramke/fluid-primitives/pull/77))
- `BaseContext` is now `DefaultContext`, marked `final` and `@internal`. A context that declares `getGlobals()` fails to load, and `globals` is a reserved prop name. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- A form rendered outside an Extbase request needs both `extensionName` and `pluginName`. Without them it throws instead of rendering fields without a prefix. ([#70](https://github.com/jramke/fluid-primitives/pull/70))
- `ui:add` writes all of a component or nothing. When one of its files already exists the command fails with a non-zero exit code, add `--force` to overwrite them all. ([#71](https://github.com/jramke/fluid-primitives/pull/71))
- `ui:attributes` never replaces an attribute the tag sets itself. A `hidden`, `style` or `role` passed by the caller no longer renders a second time. ([#85](https://github.com/jramke/fluid-primitives/pull/85))
- A component that overrides `init()` has to return `this`. ([#82](https://github.com/jramke/fluid-primitives/pull/82))
- Two hydrated roots of the same component with the same `rootId` throw. A root that is only server-rendered can repeat its `rootId`. ([#76](https://github.com/jramke/fluid-primitives/pull/76))

**Features**

- A global context holds request-wide data for the server and the client. Read it in templates with `{context.globals.locale}` and on the client with `getGlobal()`. It ships the `locale` and `debug` globals, and a project can alias its own `GlobalContextInterface`. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- `devWarn()` and `isDebug()` print warnings in development only, for example when `mount()` finds no hydration data. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- `DatePicker` and `NumberInput` fall back to the global locale. `NumberInput` now honours its `locale` prop. ([#73](https://github.com/jramke/fluid-primitives/pull/73))
- `init()` returns the instance, so a mount callback fits on one line. `AsyncList.init()` does too. ([#82](https://github.com/jramke/fluid-primitives/pull/82))
- The component templates that `ui:add` copies load their entry file through a `%componentPath%` token. The command warns when the `vite` Fluid namespace is not registered. ([#72](https://github.com/jramke/fluid-primitives/pull/72))
- `ui:add form` ships a working `onSubmit` that shows the `message` of the JSON response. The form maps the errors of a `422` to the form and to its fields. ([#77](https://github.com/jramke/fluid-primitives/pull/77))
- Clearer errors for `asChild` with several children, a part used outside its root, and a missing `extensionName` or `pluginName` on a form. ([#69](https://github.com/jramke/fluid-primitives/pull/69), [#70](https://github.com/jramke/fluid-primitives/pull/70))

**Fixes**

- `ListCollection` accepts plain objects as items with any combination of `itemToValueKey`, `itemToStringKey` and `isItemDisabledKey`. ([#67](https://github.com/jramke/fluid-primitives/pull/67))
- A form field name prefix is derived from the plugin that handles the action. It used to follow the plugin of the current request. ([#70](https://github.com/jramke/fluid-primitives/pull/70))
- The registry keys of multi-word components are camelCase (`checkboxGroup`). `ui:add checkbox-group` keeps working. ([#74](https://github.com/jramke/fluid-primitives/pull/74))

## 1.0.0-beta.3

_2026-10-14_

**Fixes**

- `ui:add` reads the component registry of a prerelease from its own tag. ([#66](https://github.com/jramke/fluid-primitives/pull/66))

## 1.0.0-beta.2

_2026-10-14_

**Features**

- New `Toggle` and `ToggleGroup` primitives. ([`c94e22a`](https://github.com/jramke/fluid-primitives/commit/c94e22a), [`53c347e`](https://github.com/jramke/fluid-primitives/commit/53c347e))
- `ui:add` reads the component registry from the tag of the installed version, so the files you copy match your library version. The registry is served from `raw.githubusercontent.com`. ([#65](https://github.com/jramke/fluid-primitives/pull/65))

## 1.0.0-beta.1

_2026-10-12_

First beta release of Fluid Primitives 1.0.

Install it with `composer require jramke/fluid-primitives:^1.0@beta` and `npm install fluid-primitives@beta`.
