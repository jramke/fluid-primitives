import { beforeEach, describe, expect, test } from 'vitest';
import { mount, mountAll, type Component } from '../../Resources/Private/Client/src/lib';

beforeEach(() => {
    window.FluidPrimitives = {
        hydrationData: {
            ui: {
                widget: {
                    auto: { autoMount: true, props: { id: 'auto', ids: {} } },
                    manual: { autoMount: false, props: { id: 'manual', ids: {} } },
                },
            },
        },
        componentInstances: {},
    } as unknown as Window['FluidPrimitives'];
});

describe('mounting components', () => {
    test('mountAll leaves components rendered with autoMount false to mount, which reaches them by rootId', () => {
        const created: string[] = [];
        const create = ({ props }: { props: { id: string } }) => {
            created.push(props.id);
            return {} as Component<unknown, unknown>;
        };

        mountAll('ui:widget', create);
        expect(created).toEqual(['auto']);

        mount('ui:widget', 'manual', create);
        expect(created).toEqual(['auto', 'manual']);
        expect(Object.keys(window.FluidPrimitives.componentInstances.ui.widget)).toEqual([
            'auto',
            'manual',
        ]);
    });
});
