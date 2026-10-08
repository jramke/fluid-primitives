---
name: fluid-primitives
description: Use when working with Fluid Primitives (jramke/fluid-primitives), the headless component library for TYPO3 Fluid - adding or using a component (accordion, dialog, tabs, select, combobox, form, field, menu, tooltip, slider, ...), writing Fluid templates with `ui:` or `primitives:` ViewHelpers, props, `ui:ref`, `ui:attributes`, or hydrating a component client-side with `mountAll` and Zag.js state machines. Gives the usage model and the live docs endpoints for exact props and API schemas.
---

# Fluid Primitives

Headless, accessible UI primitives for TYPO3 Fluid. Components render on the server with PHP/Fluid and hydrate on the client with TypeScript, using [Zag.js](https://zagjs.com/) state machines.

## Mental model

- `primitives:*` - unstyled building blocks (`<primitives:accordion.root>`, `.item`, `.trigger`, ...) shipped in the Composer package `jramke/fluid-primitives`. Client code ships in the npm package `fluid-primitives`. Keep both versions in sync.
- `ui:*` - the project's own design-system wrappers around the primitives (can also be placed under another namespace by the user). `typo3 ui:add <name>` copies a styled starting point (Fluid templates plus the `*.entry.ts` client entry) into the project, shadcn-style: the project owns the files afterwards, nothing is version-pinned.
- The `ui:` namespace is also used for the ViewHelpers (`ui:prop`, `ui:ref`, `ui:attributes`, ...) provided by `fluid-primitives`.
- Parts compose: `<ui:accordion.root>` > `.item` > `.itemTrigger` / `.itemContent`. Parts share state through a context, no prop drilling.
- Interactive components need a client entry:
    ```ts
    import { mountAll } from 'fluid-primitives';
    import { Accordion } from 'fluid-primitives/accordion';

    mountAll('ui:accordion', ({ props }) => {
        const accordion = new Accordion(props);
        accordion.init();
        return accordion;
    });
    ```

## Where to look

Always use the production host `https://fluid-primitives.com`, and fetch the Markdown (`.md`), not the HTML pages.

1. `/llms.txt` - index of every doc page with a description. Start here instead of guessing slugs.
2. `/<slug>.md` - any page as Markdown. Props tables, machine options and the JS API are already resolved.
    - `/docs/components/<name>.md` - examples, install, then `## API Reference` (Fluid props per part, rendered data attributes, machine options, JS API, keyboard support) and `## Anatomy` (part nesting).
    - `/docs/viewhelpers/<name>.md` - ViewHelper arguments (`ui:prop`, `ui:ref`, `ui:attributes`, ...).
    - `/docs/client-utilities/<name>.md` - client helpers (`extbase`, `AsyncList`, `DelayedIndicator`, `Template`).
    - `/docs/core-concepts/<name>.md` - `arguments`, `context`, `composition`, `hydration`, `styling`, `forms`, `file-structure`.
3. Registry for the styled wrappers `ui:add` copies: `/registry/components` (list), `/registry/components/<key>` (manifest), `/registry/components/<key>/files/<file>` (file content).
4. Installed source as ground truth: `vendor/jramke/fluid-primitives/Resources/Private/Primitives/<Name>/`.

## Version check

The live docs follow `main`, so they can be ahead of the installed release. Run `composer show jramke/fluid-primitives` when a documented prop or part does not exist in the project, then trust the installed source (4.) and tell the user.

## Common tasks

- **List or add a component:** `typo3 ui:list`, `typo3 ui:add accordion`. Options: `--extension` (target extension), `--path` (default `Resources/Private/Components/ui/`), `-f` / `--force` (overwrite). A component can depend on others, e.g. a field array needs `ui:add field`.
- **First-time setup** (once per project): a `ComponentCollection` extending Fluid Primitives' `AbstractComponentCollection` (not Fluid core's), the `ui` namespace appended to `$GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['ui']` in `ext_localconf.php` (append, don't overwrite), and the `jramke/fluid-primitives` site set as a dependency. Full steps: `/docs/getting-started.md`.
- **Use a component:** fetch `/docs/components/<name>.md` for examples, parts and props. Extra attributes on a tag go through `{ui:attributes()}`, class merging through `ui:cn`.
- **Write a component:** `<ui:prop>` declares props, `{ui:ref(name: 'root')}` marks elements for hydration, `<f:slot />` renders children. See `/docs/viewhelpers/<name>.md`.
- **Forms:** `/docs/core-concepts/forms.md`, plus `/docs/components/form.md` and `/docs/components/field.md`.
- **Styling:** components are unstyled. `ui:add` output uses Tailwind classes, convert them if the project uses something else. See `/docs/core-concepts/styling.md`.
