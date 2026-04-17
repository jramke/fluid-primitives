import { defineConfig } from 'tsdown';

export default defineConfig({
	entry: {
		client: './Resources/Private/Client/index.ts',
		accordion: './Resources/Private/Primitives/Accordion/Accordion.ts',
		checkbox: './Resources/Private/Primitives/Checkbox/Checkbox.ts',
		'checkbox-group': './Resources/Private/Primitives/CheckboxGroup/CheckboxGroup.ts',
		dialog: './Resources/Private/Primitives/Dialog/Dialog.ts',
		clipboard: './Resources/Private/Primitives/Clipboard/Clipboard.ts',
		collapsible: './Resources/Private/Primitives/Collapsible/Collapsible.ts',
		field: './Resources/Private/Primitives/Field/Field.ts',
		form: './Resources/Private/Primitives/Form/Form.ts',
		'navigation-menu': './Resources/Private/Primitives/NavigationMenu/NavigationMenu.ts',
		'number-input': './Resources/Private/Primitives/NumberInput/NumberInput.ts',
		popover: './Resources/Private/Primitives/Popover/Popover.ts',
		'radio-group': './Resources/Private/Primitives/RadioGroup/RadioGroup.ts',
		'scroll-area': './Resources/Private/Primitives/ScrollArea/ScrollArea.ts',
		select: './Resources/Private/Primitives/Select/Select.ts',
		switch: './Resources/Private/Primitives/Switch/Switch.ts',
		tabs: './Resources/Private/Primitives/Tabs/Tabs.ts',
		tooltip: './Resources/Private/Primitives/Tooltip/Tooltip.ts',
	},
	platform: 'browser',
	dts: true,
	outDir: './dist',
	clean: true,
	minify: false,
	ignoreWatch: ['public', 'node_modules', 'dist', 'vendor'],
	deps: {
		neverBundle: ['zod'],
	},
});
