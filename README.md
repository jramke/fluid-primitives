![The headless component library for TYPO3 Fluid](https://raw.githubusercontent.com/jramke/fluid-primitives.com/refs/heads/main/packages/docs/Resources/Public/Images/og-image.png)

# Fluid Primitives

The headless component library for TYPO3 Fluid

## Documentation

The documentation can be found at [fluid-primitives.com](https://fluid-primitives.com).

## Development

See [github.com/jramke/fluid-primitives.com](https://github.com/jramke/fluid-primitives.com) for the development setup.

## Publishing

To publish a new version, use the publish command:

```bash
npm run publish <major|minor|patch|X.Y.Z>
```

This will:
1. Run the build command
2. Bump the version in `package.json`, `composer.json`, and `ext_emconf.php`
3. Provide instructions for committing and tagging the release

Examples:
```bash
npm run publish patch    # 0.2.3 -> 0.2.4
npm run publish minor    # 0.2.3 -> 0.3.0
npm run publish major    # 0.2.3 -> 1.0.0
npm run publish 1.5.0    # 0.2.3 -> 1.5.0
```
