<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

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
            'active' => $active,
            'first' => $active && $item['value'] === $activeIds[0],
            'last' => $active && $item['value'] === end($activeIds),
        ];
    }
}
