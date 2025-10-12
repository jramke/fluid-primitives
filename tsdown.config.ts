import { defineConfig } from 'tsdown';

export default defineConfig([
	{
		entry: {
			client: './Resources/Private/Client/index.ts',

			'primitives/dialog': './Resources/Private/Primitives/Dialog/Dialog.ts',
			'primitives/dialog.entry': './Resources/Private/Primitives/Dialog/Dialog.entry.ts',

			'primitives/clipboard': './Resources/Private/Primitives/Clipboard/Clipboard.ts',
			'primitives/clipboard.entry':
				'./Resources/Private/Primitives/Clipboard/Clipboard.entry.ts',

			'primitives/collapsible': './Resources/Private/Primitives/Collapsible/Collapsible.ts',
			'primitives/collapsible.entry':
				'./Resources/Private/Primitives/Collapsible/Collapsible.entry.ts',

			'primitives/scroll-area': './Resources/Private/Primitives/ScrollArea/ScrollArea.ts',
			'primitives/scroll-area.entry':
				'./Resources/Private/Primitives/ScrollArea/ScrollArea.entry.ts',

			'primitives/select': './Resources/Private/Primitives/Select/Select.ts',
			'primitives/select.entry': './Resources/Private/Primitives/Select/ScrollArea.entry.ts',

			'primitives/tooltip': './Resources/Private/Primitives/Tooltip/Tooltip.ts',
			'primitives/tooltip.entry': './Resources/Private/Primitives/Tooltip/Tooltip.entry.ts',
		},
		platform: 'browser',
		dts: true,
		outDir: './Resources/Public/JavaScript/dist',
		clean: true,
		minify: true,
	},
]);
