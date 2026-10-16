import { createMachine } from '@zag-js/core';
import { afterEach, describe, expect, test } from 'vitest';
import { Component, Machine } from '../../Resources/Private/Client';

interface ProbeProps {
    id: string;
    label?: string;
}

const probeMachine = createMachine<{
    props: ProbeProps;
    state: 'idle';
    event: { type: 'PING' };
    action: string;
    effect: string;
}>({
    initialState: () => 'idle',
    states: { idle: {} },
});

class Probe extends Component<ProbeProps, { label: string | undefined }> {
    static componentName = 'probe';

    // a field initializer of the subclass: it only exists once `super()` returned
    calls: string[] = [];

    transformProps(props: ProbeProps) {
        this.calls.push('transformProps');
        return { ...props, label: `${props.label}!` };
    }

    initHydrator(props: ProbeProps) {
        this.calls.push(`initHydrator(${props.label})`);
        return super.initHydrator(props);
    }

    initMachine(props: ProbeProps) {
        this.calls.push(`initMachine(${props.label})`);
        return new Machine(probeMachine, props);
    }

    initApi() {
        this.calls.push('initApi');
        return { label: this.machine.prop('label') };
    }

    render() {
        this.calls.push(`render(${this.api.label})`);
    }
}

let probe: Probe | undefined;

afterEach(() => {
    probe?.destroy();
    probe = undefined;
});

describe('Component lifecycle', () => {
    test('nothing is built before init(), then the transformed props reach every step once', () => {
        probe = new Probe({ id: 'p1', label: 'raw' });

        expect(probe.calls).toEqual([]);
        for (const member of ['machine', 'api', 'hydrator', 'userProps'] as const) {
            expect(() => probe![member]).toThrow(`\`${member}\` was accessed before init()`);
        }

        probe.init();

        expect(probe.calls.slice(0, 5)).toEqual([
            'transformProps',
            'initHydrator(raw!)',
            'initMachine(raw!)',
            'initApi',
            'render(raw!)',
        ]);
        expect(probe.calls.filter(call => call === 'transformProps')).toHaveLength(1);
        expect(probe.userProps.label).toBe('raw!');
        expect(probe.hydrator.rootId).toBe('p1');
    });

    test('init() returns the instance, so a mount callback can return it directly', () => {
        probe = new Probe({ id: 'p1' });

        expect(probe.init()).toBe(probe);
    });

    test('a prop change and refresh() both connect a fresh api and render again', () => {
        probe = new Probe({ id: 'p1', label: 'raw' });
        probe.init();
        probe.calls.length = 0;

        probe.updateProps({ label: 'next' });
        expect(probe.calls).toEqual(['initApi', 'render(next)']);

        probe.calls.length = 0;
        probe.refresh();
        expect(probe.calls).toEqual(['initApi', 'render(next)']);
    });
});
