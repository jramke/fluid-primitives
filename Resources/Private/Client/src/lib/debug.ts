import { getGlobal } from './hydration';

/** Whether the server rendered the page in TYPO3's development context. */
export const isDebug = (): boolean => getGlobal<boolean>('debug', false) === true;

/** Warns in the console, but only on a page rendered in the development context. */
export function devWarn(message: string, ...details: unknown[]): void {
    if (isDebug()) {
        console.warn(`[fluid-primitives] ${message}`, ...details);
    }
}
