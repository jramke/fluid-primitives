<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Utility\Typed;

class TocContext extends AbstractComponentContext
{
    /**
     * @param array{ value:string } $item
     */
    public function getItemState(array $item): object
    {
        $activeIds = array_values(Typed::arrayOrNull($this->get('defaultActiveIds')) ?? []);
        $active = in_array($item['value'], $activeIds, strict: true);

        return (object)[
            'depth' => $this->getDepth($item['value']),
            'active' => $active,
            'first' => $active && $item['value'] === $activeIds[0],
            'last' => $active && $item['value'] === end($activeIds),
        ];
    }

    private function getDepth(string $value): int
    {
        // The entries of the items prop are template data, Typed narrows each one below.
        // @mago-expect analysis:mixed-assignment
        foreach (Typed::arrayOrNull($this->get('items')) ?? [] as $entry) {
            $entry = Typed::arrayOrNull($entry) ?? [];
            if (Typed::stringOrNull($entry['value'] ?? null) === $value) {
                return Typed::int($entry['depth'] ?? null, 2);
            }
        }

        throw new InvalidPropValueException(
            sprintf('The toc item "%s" is not one of the items of the toc.root.', $value),
            1_788_100_052,
        );
    }
}
