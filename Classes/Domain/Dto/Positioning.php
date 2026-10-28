<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use Jramke\FluidPrimitives\Enum\Placement;
use Jramke\FluidPrimitives\Enum\PositioningStrategy;
use JsonSerializable;

/**
 * The options that position a floating element (Popover, Tooltip, Menu, Select, Combobox, DatePicker). It is the
 * serializable part of the `PositioningOptions` of Zag.js, so the callbacks (`boundary`, `getAnchorElement`,
 * `onPositioned`, ...) are not part of it. Set those on the client.
 *
 * Only the options that are set reach the client.
 */
final readonly class Positioning implements JsonSerializable
{
    // The constructor mirrors the options of the positioning schema one to one.
    // @mago-expect lint:excessive-parameter-list
    /**
     * @param array{mainAxis?: int|float, crossAxis?: int|float}|null $offset
     * @param bool|list<Placement>|null $flip `true` to flip to any side, a list of placements to try in that order.
     */
    public function __construct(
        public ?Placement $placement = null,
        public ?PositioningStrategy $strategy = null,
        public int|float|null $gutter = null,
        public int|float|null $shift = null,
        public ?array $offset = null,
        public int|float|null $overflowPadding = null,
        public int|float|null $arrowPadding = null,
        public bool|array|null $flip = null,
        public ?bool $slide = null,
        public ?bool $overlap = null,
        public ?bool $sameWidth = null,
        public ?bool $fitViewport = null,
        public ?bool $hideWhenDetached = null,
        public ?bool $sizeMiddleware = null,
    ) {}

    /**
     * The wire shape sent to the client: only the options that are set. This docblock is the source of the
     * generated TypeScript type.
     *
     * @return array{
     *     placement?: string,
     *     strategy?: string,
     *     gutter?: int|float,
     *     shift?: int|float,
     *     offset?: array{mainAxis?: int|float, crossAxis?: int|float},
     *     overflowPadding?: int|float,
     *     arrowPadding?: int|float,
     *     flip?: bool|list<string>,
     *     slide?: bool,
     *     overlap?: bool,
     *     sameWidth?: bool,
     *     fitViewport?: bool,
     *     hideWhenDetached?: bool,
     *     sizeMiddleware?: bool,
     * }
     */
    public function jsonSerialize(): array
    {
        // The enums encode as their string values, which is the documented wire shape.
        // @mago-expect analysis:invalid-return-statement
        return array_filter(get_object_vars($this), static fn(mixed $option): bool => $option !== null);
    }
}
