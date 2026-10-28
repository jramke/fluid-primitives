<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Domain\Dto\Positioning;
use Jramke\FluidPrimitives\Enum\Placement;
use Jramke\FluidPrimitives\Enum\PositioningStrategy;
use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Create the `positioning` of a floating component like [Popover](/docs/components/popover),
 * [Tooltip](/docs/components/tooltip) or [Select](/docs/components/select).
 *
 * The arguments are the serializable options of the Zag.js [positioning](https://zagjs.com/overview/positioning)
 * schema. A wrong placement, an unknown offset key or a value of the wrong type fails when the template renders
 * instead of silently doing nothing on the client. Only the options you set are sent to the client.
 *
 * ## Example
 * ```html
 * <ui:popover.root positioning="{ui:positioning(placement: 'right-start', gutter: 12)}">
 *     ...
 * </ui:popover.root>
 * ```
 *
 * With an offset and a list of placements to flip to:
 *
 * ```html
 * <ui:tooltip.root
 *     positioning="{ui:positioning(
 *         placement: 'top',
 *         offset: {mainAxis: 4, crossAxis: 8},
 *         flip: {0: 'bottom', 1: 'right'}
 *     )}"
 * >
 *     ...
 * </ui:tooltip.root>
 * ```
 */
class PositioningViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'placement',
            'string',
            'Where the floating element is placed, e.g. `bottom-start`: `top`, `right`, `bottom` or `left`, with an optional `-start` or `-end`.',
        );
        $this->registerArgument('strategy', 'string', 'The CSS positioning strategy: `absolute` or `fixed`.');
        $this->registerArgument(
            'gutter',
            'int|float|string',
            'The gap between the reference and the floating element.',
        );
        $this->registerArgument('shift', 'int|float|string', 'The offset along the secondary axis.');
        $this->registerArgument(
            'offset',
            'array',
            'The offset as `{mainAxis: number, crossAxis: number}`, both keys are optional.',
        );
        $this->registerArgument(
            'overflowPadding',
            'int|float|string',
            'The virtual padding around the viewport edges to check for overflow.',
        );
        $this->registerArgument(
            'arrowPadding',
            'int|float|string',
            'The minimum padding between the arrow and the corner of the floating element.',
        );
        $this->registerArgument(
            'flip',
            'boolean|array',
            'Whether to flip the placement when it overflows. A list of placements limits it to those, in that order.',
        );
        $this->registerArgument(
            'slide',
            'boolean',
            'Whether the floating element slides along the edge when it overflows.',
        );
        $this->registerArgument(
            'overlap',
            'boolean',
            'Whether the floating element can overlap the reference element.',
        );
        $this->registerArgument(
            'sameWidth',
            'boolean',
            'Whether the floating element gets the width of the reference element.',
        );
        $this->registerArgument('fitViewport', 'boolean', 'Whether the floating element is kept inside the viewport.');
        $this->registerArgument(
            'hideWhenDetached',
            'boolean',
            'Whether the floating element is hidden when the reference element is detached.',
        );
        $this->registerArgument(
            'sizeMiddleware',
            'boolean',
            'Whether to compute the size CSS variables. Turn it off for a scroll performance gain when neither `sameWidth` nor `fitViewport` is set.',
        );
    }

    public function render(): Positioning
    {
        // Narrowed right below: a list of placements or a boolean, which no single Typed:: call covers.
        // @mago-expect analysis:mixed-assignment
        $flip = $this->arguments['flip'];
        $placement = Typed::stringOrNull($this->arguments['placement']);
        $strategy = Typed::stringOrNull($this->arguments['strategy']);

        return new Positioning(
            placement: $placement === null ? null : $this->toPlacement($placement),
            strategy: $strategy === null ? null : $this->toStrategy($strategy),
            gutter: $this->number('gutter'),
            shift: $this->number('shift'),
            offset: $this->offset(),
            overflowPadding: $this->number('overflowPadding'),
            arrowPadding: $this->number('arrowPadding'),
            flip: is_array($flip)
                ? array_values(array_map(fn(mixed $item): Placement => $this->toPlacement(Typed::string($item)), $flip))
                : Typed::boolOrNull($flip),
            slide: Typed::boolOrNull($this->arguments['slide']),
            overlap: Typed::boolOrNull($this->arguments['overlap']),
            sameWidth: Typed::boolOrNull($this->arguments['sameWidth']),
            fitViewport: Typed::boolOrNull($this->arguments['fitViewport']),
            hideWhenDetached: Typed::boolOrNull($this->arguments['hideWhenDetached']),
            sizeMiddleware: Typed::boolOrNull($this->arguments['sizeMiddleware']),
        );
    }

    private function toPlacement(string $value): Placement
    {
        return (
            Placement::tryFrom($value) ?? throw new \InvalidArgumentException(
                sprintf(
                    'Unknown placement "%s". Use one of: %s.',
                    $value,
                    implode(', ', array_map(static fn(Placement $case): string => $case->value, Placement::cases())),
                ),
                1_788_400_001,
            )
        );
    }

    private function toStrategy(string $value): PositioningStrategy
    {
        return (
            PositioningStrategy::tryFrom($value) ?? throw new \InvalidArgumentException(
                sprintf(
                    'Unknown positioning strategy "%s". Use one of: %s.',
                    $value,
                    implode(', ', array_map(
                        static fn(PositioningStrategy $case): string => $case->value,
                        PositioningStrategy::cases(),
                    )),
                ),
                1_788_400_002,
            )
        );
    }

    /**
     * @return array{mainAxis?: int|float, crossAxis?: int|float}|null
     */
    private function offset(): ?array
    {
        $offset = Typed::arrayOrNull($this->arguments['offset']);
        if ($offset === null) {
            return null;
        }

        $unknownKeys = array_diff(array_keys($offset), ['mainAxis', 'crossAxis']);
        if ($unknownKeys !== []) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown offset key "%s". The offset accepts "mainAxis" and "crossAxis".',
                    Typed::string(reset($unknownKeys)),
                ),
                1_788_400_003,
            );
        }

        $axes = [];
        foreach (['mainAxis', 'crossAxis'] as $axis) {
            if (!array_key_exists($axis, $offset)) {
                continue;
            }

            $axes[$axis] = $this->toNumber($offset[$axis], 'offset');
        }

        return $axes;
    }

    private function number(string $argument): int|float|null
    {
        // Narrowed in toNumber(): an int, a float or a numeric string.
        // @mago-expect analysis:mixed-assignment
        $value = $this->arguments[$argument];

        return $value === null ? null : $this->toNumber($value, $argument);
    }

    private function toNumber(mixed $value, string $argument): int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return Typed::intOrNull($value) ?? (float)$value;
        }

        throw new \InvalidArgumentException(
            sprintf('The "%s" argument of ui:positioning must be a number.', $argument),
            1_788_400_004,
        );
    }
}
