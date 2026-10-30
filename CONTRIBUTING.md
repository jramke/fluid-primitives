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

**Type** decides where, or whether, the change shows up in the changelog:

| Type                                                        | Changelog                               |
| ----------------------------------------------------------- | --------------------------------------- |
| `feat`                                                      | **Features**                            |
| `fix`, `perf`                                               | **Fixes**                               |
| `feat!`, `fix!`, or a `BREAKING CHANGE:` footer             | **Breaking changes**, whatever the type |
| `docs`, `test`, `refactor`, `style`, `build`, `ci`, `chore` | No entry, unless a user would notice it |

**Scope** names the part of the library the change is about. It is the same word the changelog entry starts
with, so you pick it once:

| The change is about                                                                                                              | Commit scope                   | Changelog prefix                 |
| -------------------------------------------------------------------------------------------------------------------------------- | ------------------------------ | -------------------------------- |
| A component, including its registry copy                                                                                         | `select`, `date-picker`, `toc` | `**Select:**`, `**DatePicker:**` |
| A few components                                                                                                                 | `select,combobox`              | `**Select, Combobox:**`          |
| A ViewHelper that is not part of a component                                                                                     | `ui:attributes`, `ui:call`     | ``**`ui:attributes`:**``         |
| The client: `Component`, `Machine`, `extbase`, `mountAll()` and the other utilities                                              | `client`                       | `**Client:**`                    |
| `ui:add` and the other commands, and the registry as a whole                                                                     | `cli`                          | `**CLI:**`                       |
| The rest of the PHP side that every component shares: contexts, hydration, `ListCollection`, exceptions, requirements, packaging | `core`                         | `**Core:**`                      |

A component scope is the name of its docs page (`date-picker`), a changelog prefix is the name you write in prose
(`DatePicker`). A change that touches more than a handful of components names the area they share (`form`, `core`)
instead of all of them. Leave the scope off a commit that has no single area. A changelog entry always has one.

Subject: imperative, no full stop ("add", not "added"). Say what changes for the user when you can, the body
is for the why.

## Changelog

Every change a user would notice gets an entry under `## Unreleased` in [`CHANGELOG.md`](CHANGELOG.md), in the
same commit or pull request. When you use Claude Code in this repository, the `changelog` skill writes it.

```md
- **Select:** An option like `<img src=x onerror=...>` no longer runs as markup. ([#87](https://github.com/jramke/fluid-primitives/pull/87))
```

One bullet in the group of its type (`**Breaking changes**`, `**Features**`, `**Fixes**`), starting with the
bold changelog prefix from the table above. Keep the bullets of one scope together.
