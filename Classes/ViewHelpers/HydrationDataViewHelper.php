<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Declare arbitrarily hydration data that should be exposed to the client.
 * 
 * This ViewHelper allows you to define hydration data for like for a component that will be sent to the client for hydration purposes.
 * The data is registered in the same registry and with the same structure as for component hydration data, so it can be consumed by the same hydration mechanisms.
 * 
 * See [Hydration Guide](/docs/core-concepts/hydration) for more information about how hydration data is used.
 * 
 * Example:
 * ```html
 * <ui:hydrationData name="some-block" props="{someProp: 'someValue', anotherProp: 123}" />
 * ```
 */
class HydrationDataViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('name', 'string', 'The name under which the hydration data should be exposed', true);
        $this->registerArgument('id', 'string', 'An optional identifier for the hydration data. If not provided, a unique id will be generated.', false);
        $this->registerArgument('props', 'mixed', 'The props/data to expose to the client', true);
        $this->registerArgument('controlled', 'boolean', 'Whether the hydration data is for a controlled component', false, false);
    }

    public function getContentArgumentName(): string
    {
        return 'props';
    }

    public function render(): void
    {
        if (empty($this->arguments['name'])) {
            throw new \RuntimeException('The "name" argument is required', 1766249544);
        }

        $props = $this->normalizeData($this->renderChildren() ?? []);
        unset($props['id']); // Remove potential id from client props as it is handled separately

        $id = $this->arguments['id'] ?? ComponentUtility::id();

        $data = [
            'controlled' => $this->arguments['controlled'],
            'props' => [
                'id' => $id,
                ...$props,
            ]
        ];

        $registry = HydrationRegistry::getInstance();
        $registry->add(
            $this->arguments['name'],
            $id,
            $data
        );
    }

    private function normalizeData(mixed $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if ($data instanceof \Traversable) {
            return iterator_to_array($data);
        }

        if ($data instanceof \JsonSerializable) {
            return (array)$data->jsonSerialize();
        }

        if (is_object($data) && method_exists($data, 'toArray')) {
            return $data->toArray();
        }

        throw new \RuntimeException(
            sprintf(
                'Hydration data must be an array, Traversable, JsonSerializable, or object with toArray(), %s given',
                get_debug_type($data)
            ),
            1766249545
        );
    }
}
