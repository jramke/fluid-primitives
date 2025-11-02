import { defineConfig } from 'tsdown';

export default defineConfig([
	{
		entry: {
			client: './Resources/Private/Client/index.ts',

			accordion: './Resources/Private/Primitives/Accordion/Accordion.ts',
			'accordion.entry': './Resources/Private/Primitives/Accordion/Accordion.entry.ts',

			dialog: './Resources/Private/Primitives/Dialog/Dialog.ts',
			'dialog.entry': './Resources/Private/Primitives/Dialog/Dialog.entry.ts',

			clipboard: './Resources/Private/Primitives/Clipboard/Clipboard.ts',
			'clipboard.entry': './Resources/Private/Primitives/Clipboard/Clipboard.entry.ts',

			collapsible: './Resources/Private/Primitives/Collapsible/Collapsible.ts',
			'collapsible.entry': './Resources/Private/Primitives/Collapsible/Collapsible.entry.ts',

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
