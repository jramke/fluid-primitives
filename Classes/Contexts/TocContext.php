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
        $activeIds = array_values(Typed::array($this->get('defaultActiveIds')));
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
        foreach (Typed::array($this->get('items')) as $entry) {
            $entry = Typed::array($entry);
            if (Typed::string($entry['value'] ?? null) !== $value) {
                continue;
            }

            return (
                Typed::intOrNull($entry['depth'] ?? null) ?? throw new InvalidPropValueException(
                    sprintf('The entry "%s" of the toc items needs an integer depth, like 2 for an h2.', $value),
                    1_788_100_053,
                )
            );
        }

        throw new InvalidPropValueException(
            sprintf('The toc item "%s" is not one of the items of the toc.root.', $value),
            1_788_100_052,
        );
    }
}
