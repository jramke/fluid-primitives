import { defineConfig } from 'tsdown';

export default defineConfig([
	{
		entry: {
			client: './Resources/Private/Client/index.ts',

			accordion: './Resources/Private/Primitives/Accordion/Accordion.ts',
			'accordion.entry': './Resources/Private/Primitives/Accordion/Accordion.entry.ts',

			checkbox: './Resources/Private/Primitives/Checkbox/Checkbox.ts',
			'checkbox.entry': './Resources/Private/Primitives/Checkbox/Checkbox.entry.ts',

			dialog: './Resources/Private/Primitives/Dialog/Dialog.ts',
			'dialog.entry': './Resources/Private/Primitives/Dialog/Dialog.entry.ts',

			clipboard: './Resources/Private/Primitives/Clipboard/Clipboard.ts',
			'clipboard.entry': './Resources/Private/Primitives/Clipboard/Clipboard.entry.ts',

			collapsible: './Resources/Private/Primitives/Collapsible/Collapsible.ts',
			'collapsible.entry': './Resources/Private/Primitives/Collapsible/Collapsible.entry.ts',

			field: './Resources/Private/Primitives/Field/Field.ts',
			'field.entry': './Resources/Private/Primitives/Field/Field.entry.ts',

			form: './Resources/Private/Primitives/Form/Form.ts',
			'form.entry': './Resources/Private/Primitives/Form/Form.entry.ts',

			'number-input': './Resources/Private/Primitives/NumberInput/NumberInput.ts',
			'number-input.entry': './Resources/Private/Primitives/NumberInput/NumberInput.entry.ts',

			popover: './Resources/Private/Primitives/Popover/Popover.ts',
			'popover.entry': './Resources/Private/Primitives/Popover/Popover.entry.ts',

			'radio-group': './Resources/Private/Primitives/RadioGroup/RadioGroup.ts',
			'radio-group.entry': './Resources/Private/Primitives/RadioGroup/RadioGroup.entry.ts',

			'scroll-area': './Resources/Private/Primitives/ScrollArea/ScrollArea.ts',
			'scroll-area.entry': './Resources/Private/Primitives/ScrollArea/ScrollArea.entry.ts',

			select: './Resources/Private/Primitives/Select/Select.ts',
			'select.entry': './Resources/Private/Primitives/Select/Select.entry.ts',

			tabs: './Resources/Private/Primitives/Tabs/Tabs.ts',
			'tabs.entry': './Resources/Private/Primitives/Tabs/Tabs.entry.ts',

			tooltip: './Resources/Private/Primitives/Tooltip/Tooltip.ts',
			'tooltip.entry': './Resources/Private/Primitives/Tooltip/Tooltip.entry.ts',
		},
		platform: 'browser',
		dts: true,
		outDir: './Resources/Public/JavaScript/dist',
		clean: true,
		minify: true,
	},
]);
