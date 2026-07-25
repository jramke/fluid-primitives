/**
 * Idiomorph does not ship its own TypeScript declarations, so this provides a
 * minimal ambient module declaration covering the subset of its API this
 * library uses. See https://github.com/bigskysoftware/idiomorph for the full
 * API surface.
 */
declare module 'idiomorph' {
    export interface IdiomorphCallbacks {
        beforeNodeAdded?(node: Node): boolean | void;
        afterNodeAdded?(node: Node): void;
        beforeNodeMorphed?(oldNode: Node, newNode: Node): boolean | void;
        afterNodeMorphed?(oldNode: Node, newNode: Node): void;
        beforeNodeRemoved?(node: Node): boolean | void;
        afterNodeRemoved?(node: Node): void;
        beforeAttributeUpdated?(
            attributeName: string,
            node: Element,
            mutationType: 'update' | 'remove'
        ): boolean | void;
    }

    export interface IdiomorphOptions {
        morphStyle?: 'outerHTML' | 'innerHTML';
        ignoreActive?: boolean;
        ignoreActiveValue?: boolean;
        restoreFocus?: boolean;
        callbacks?: IdiomorphCallbacks;
        head?: {
            style?: 'merge' | 'append' | 'morph' | 'none';
        };
    }

    export const Idiomorph: {
        morph(
            existingNode: Element | Document,
            newContent: Element | string | Node[],
            options?: IdiomorphOptions
        ): Node[] | undefined;
        defaults: IdiomorphOptions;
    };
}
