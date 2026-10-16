import { mountAll } from 'fluid-primitives';
import { Field } from 'fluid-primitives/field';
import { FieldArray } from 'fluid-primitives/field-array';
import { Input } from 'fluid-primitives/input';

// A row appended client-side (via api.append()) contains its own nested Field/Input instances
// that aren't hydrated yet - `mountAll` is safe to call more than once (already-mounted instances
// are skipped), so re-running these two on every `itemadded` picks up whatever a row's own
// itemTemplate contains without FieldArray needing to know about Field/Input itself.
function mountRowComponents() {
    mountAll('ui:field', ({ props }) => new Field(props).init());
    mountAll('ui:input', ({ props }) => new Input(props).init());
}

mountAll('ui:fieldArray', ({ props }) =>
    new FieldArray({ ...props, onItemAdded: mountRowComponents }).init()
);
