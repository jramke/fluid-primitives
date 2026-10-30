# Contributing

## Commit messages

Write commits as [Conventional Commits](https://www.conventionalcommits.org): `type(scope): subject`. We prefer
the format and do not enforce it, a plain imperative sentence is better than a forced prefix. The point of the
format is that a commit maps to a [changelog](CHANGELOG.md) entry without anyone re-reading the diff.

```txt
feat(toc): add the Toc primitive
fix(field-array): rename the fields of a row you remove
feat(ui:attributes)!: never replace an attribute the tag sets itself
docs(select): show a grouped list
```

**Type** decides whether the change shows up in the changelog, and where:

| Type                                                        | Changelog                                              |
| ----------------------------------------------------------- | ------------------------------------------------------ |
| `feat`, `fix`, `perf`                                       | An entry under the heading of its scope                |
| `feat!`, `fix!`, or a `BREAKING CHANGE:` footer             | An entry under **Breaking changes**, whatever the type |
| `docs`, `test`, `refactor`, `style`, `build`, `ci`, `chore` | No entry, unless a user would notice it                |

**Scope** names the part of the library the change is about. It is the heading of the changelog entry, so you pick
it once:

| The change is about                                                                                                   | Commit scope                   | Changelog heading              |
| --------------------------------------------------------------------------------------------------------------------- | ------------------------------ | ------------------------------ |
| A component, including its registry copy                                                                              | `select`, `date-picker`, `toc` | `### Select`, `### DatePicker` |
| A ViewHelper that is not part of a component                                                                          | `ui:attributes`, `ui:call`     | ``### `ui:attributes` ``       |
| The client: `Component`, `Machine`, `extbase`, `mountAll()` and the other utilities                                   | `client`                       | `### Client`                   |
| `ui:add` and the other commands, and the registry as a whole                                                          | `cli`                          | `### CLI`                      |
| The rest of the PHP side that every component shares: contexts, hydration, `ListCollection`, exceptions, requirements | `core`                         | `### Core`                     |
| Several components, or the package as a whole: packaging, translations, anything that no single heading holds         | `select,combobox`, or no scope | `### General changes`          |

A component scope is the name of its docs page (`date-picker`), a changelog heading is the name you write in prose
(`DatePicker`). Leave the scope off a commit that has no single area, it becomes a general change.

Subject: imperative, no full stop ("add", not "added"). Say what changes for the user when you can, the body
is for the why.

## Changelog

Every change a user would notice gets an entry under `## Unreleased` in [`CHANGELOG.md`](CHANGELOG.md), in the
same commit or pull request. When you use Claude Code in this repository, the `changelog` skill writes it.

A release has `### Breaking changes` first, then `### General changes`, then one heading per scope in alphabetical
order. An entry goes under exactly one heading, the table above says which. Under a scope heading the bullet does
not repeat the scope. Under **Breaking changes** and **General changes** it starts with the bold scope, because
the heading no longer names it: `**Select, Combobox:** ...`. Features come before fixes inside a heading.

```md
### Breaking changes

- **Core:** `typo3fluid/fluid` has to be `^5.2`. ([#90](https://github.com/jramke/fluid-primitives/pull/90))

### Select

- An option like `<img src=x onerror=...>` no longer runs as markup. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
```
