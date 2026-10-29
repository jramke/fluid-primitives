<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Domain\Dto\TocItem;
use Jramke\FluidPrimitives\Exception\InvalidArgumentException;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Utility\Typed;

class TocContext extends AbstractComponentContext
{
    /** @var array<string, TocItem>|null */
    private ?array $items = null;

    /**
     * @param array{ value:string } $item
     */
    public function getItemState(array $item): object
    {
        $value = $item['value'];
        $tocItem = $this->getItems()[$value] ?? throw new InvalidPropValueException(
            sprintf('The toc item "%s" is not one of the items of the toc.root.', $value),
            1_788_100_052,
        );
        $activeIds = array_values(Typed::array($this->get('defaultActiveIds')));
        $active = in_array($value, $activeIds, strict: true);

        return (object)[
            'depth' => $tocItem->depth,
            'active' => $active,
            'first' => $active && $value === $activeIds[0],
            'last' => $active && $value === end($activeIds),
        ];
    }

    /**
     * The items prop takes TocItem objects and arrays alike.
     *
     * @return array<string, TocItem> By value
     */
    private function getItems(): array
    {
        if ($this->items !== null) {
            return $this->items;
        }

        $this->items = [];
        // The entries of the items prop are template data, each one is narrowed below.
        // @mago-expect analysis:mixed-assignment
        foreach (Typed::array($this->get('items')) as $index => $entry) {
            try {
                $item = $entry instanceof TocItem ? $entry : TocItem::fromArray(Typed::array($entry));
            } catch (InvalidArgumentException $exception) {
                throw new InvalidPropValueException(
                    sprintf('Entry %s of the toc items is not valid: %s', $index, $exception->getMessage()),
                    (int)$exception->getCode(),
                    $exception,
                );
            }

            $this->items[$item->value] = $item;
        }

        return $this->items;
    }
}
