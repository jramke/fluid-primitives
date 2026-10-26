<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Domain\Dto;

use Jramke\FluidPrimitives\Contracts\ConvertibleArgumentInterface;
use Jramke\FluidPrimitives\Utility\Typed;
use JsonSerializable;

/**
 * The positioning options of a floating element (Popover, Tooltip, Select, Combobox, Menu, DatePicker),
 * as Zag.js' `PositioningOptions` take them. Only the options that survive the trip to the browser as
 * JSON are here: the ones that take a function or an element (`boundary`, `middleware`,
 * `getAnchorElement`, `onComplete`, ...) are set in client code.
 *
 * A prop declared `array|Positioning` takes the options as a plain array too, which
 * {@see fromArgument()} turns into an instance, so a typo in an option name is an error instead of
 * a silently ignored key.
 */
final readonly class Positioning implements ConvertibleArgumentInterface, JsonSerializable
{
    public const array PLACEMENTS = [
        'top',
        'top-start',
        'top-end',
        'bottom',
        'bottom-start',
        'bottom-end',
        'left',
        'left-start',
        'left-end',
        'right',
        'right-start',
        'right-end',
    ];

    public const array STRATEGIES = ['absolute', 'fixed'];

    /** The one `boundary` a string can name, the others are elements or functions. */
    public const array BOUNDARIES = ['clipping-ancestors'];

    private const array OPTIONS = [
        'restoreStyles',
        'applyStyles',
        'hideWhenDetached',
        'strategy',
        'placement',
        'offset',
        'gutter',
        'shift',
        'overflowPadding',
        'arrowPadding',
        'flip',
        'slide',
        'overlap',
        'sameWidth',
        'fitViewport',
        'sizeMiddleware',
        'boundary',
        'listeners',
    ];

    private const array BOOLEAN_OPTIONS = [
        'restoreStyles',
        'applyStyles',
        'hideWhenDetached',
        'slide',
        'overlap',
        'sameWidth',
        'fitViewport',
        'sizeMiddleware',
    ];

    private const array NUMBER_OPTIONS = ['gutter', 'shift', 'overflowPadding', 'arrowPadding'];

    private const array OFFSET_KEYS = ['mainAxis', 'crossAxis'];

    private const array LISTENER_KEYS = [
        'ancestorScroll',
        'ancestorResize',
        'elementResize',
        'layoutShift',
        'animationFrame',
    ];

    /**
     * @param string|null $placement The initial placement of the floating element, one of {@see PLACEMENTS}.
     * @param string|null $strategy `absolute` or `fixed`.
     * @param array{mainAxis?: float|int, crossAxis?: float|int}|null $offset
     * @param float|int|null $gutter The gap between the reference and the floating element on the main axis.
     * @param float|int|null $shift The offset on the secondary axis.
     * @param float|int|null $overflowPadding The virtual padding around the viewport edges to check for overflow.
     * @param float|int|null $arrowPadding The minimum padding between the arrow and the corner of the floating element.
     * @param bool|list<string>|null $flip Whether to flip the placement, or the placements to flip to.
     * @param string|null $boundary The overflow boundary, `clipping-ancestors`.
     * @param bool|array{ancestorScroll?: bool, ancestorResize?: bool, elementResize?: bool, layoutShift?: bool, animationFrame?: bool}|null $listeners
     *   Whether to update the position on scroll, resize and layout changes, or which of them.
     */
    // Zag's PositioningOptions is this flat, and a named argument per option is what `fromArgument()` spreads into.
    // @mago-expect lint:excessive-parameter-list
    public function __construct(
        public ?bool $restoreStyles = null,
        public ?bool $applyStyles = null,
        public ?bool $hideWhenDetached = null,
        public ?string $strategy = null,
        public ?string $placement = null,
        public ?array $offset = null,
        public int|float|null $gutter = null,
        public int|float|null $shift = null,
        public int|float|null $overflowPadding = null,
        public int|float|null $arrowPadding = null,
        public bool|array|null $flip = null,
        public ?bool $slide = null,
        public ?bool $overlap = null,
        public ?bool $sameWidth = null,
        public ?bool $fitViewport = null,
        public ?bool $sizeMiddleware = null,
        public ?string $boundary = null,
        public bool|array|null $listeners = null,
    ) {
        $this->assertOneOf('strategy', $strategy, self::STRATEGIES);
        $this->assertOneOf('placement', $placement, self::PLACEMENTS);
        $this->assertOneOf('boundary', $boundary, self::BOUNDARIES);
        if (is_array($flip)) {
            foreach ($flip as $flipPlacement) {
                $this->assertOneOf('flip', $flipPlacement, self::PLACEMENTS);
            }
        }

        $this->assertKeys('offset', $offset, self::OFFSET_KEYS);
        if (is_array($listeners)) {
            $this->assertKeys('listeners', $listeners, self::LISTENER_KEYS);
        }
    }

    /**
     * Takes the options as an array, or just the placement as a string: `top-start` is
     * `{placement: 'top-start'}`.
     */
    public static function fromArgument(mixed $value): static
    {
        if (is_string($value)) {
            $value = ['placement' => $value];
        }

        if (!is_array($value)) {
            throw new \InvalidArgumentException(
                'The positioning options must be an array like {placement: \'bottom-start\', gutter: 8} or a placement like \'bottom-start\'.',
                1_788_400_002,
            );
        }

        $unknownOptions = array_diff(array_keys($value), self::OPTIONS);
        if ($unknownOptions !== []) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Unknown positioning option "%s". The options are: %s.',
                    implode('", "', array_map(strval(...), $unknownOptions)),
                    implode(', ', self::OPTIONS),
                ),
                1_788_400_003,
            );
        }

        // Each option is whatever the template gave; the match below narrows it per option.
        // @mago-expect analysis:mixed-assignment
        foreach ($value as $name => $option) {
            $value[$name] = match (true) {
                $option === null => null,
                $name === 'listeners' && is_array($option) => $option,
                $name === 'listeners' => Typed::boolOrNull($option) ?? self::invalid($name, 'a boolean or an array'),
                in_array($name, self::BOOLEAN_OPTIONS, strict: true) => Typed::boolOrNull($option) ?? self::invalid(
                    $name,
                    'a boolean',
                ),
                in_array($name, self::NUMBER_OPTIONS, strict: true) => Typed::intOrNull($option) ?? Typed::floatOrNull(
                    $option,
                ) ?? self::invalid($name, 'a number'),
                default => $option,
            };
        }

        return new self(...$value);
    }

    /**
     * @return array{
     *     restoreStyles?: bool,
     *     applyStyles?: bool,
     *     hideWhenDetached?: bool,
     *     strategy?: string,
     *     placement?: string,
     *     offset?: array{mainAxis?: float|int, crossAxis?: float|int},
     *     gutter?: float|int,
     *     shift?: float|int,
     *     overflowPadding?: float|int,
     *     arrowPadding?: float|int,
     *     flip?: bool|list<string>,
     *     slide?: bool,
     *     overlap?: bool,
     *     sameWidth?: bool,
     *     fitViewport?: bool,
     *     sizeMiddleware?: bool,
     *     boundary?: string,
     *     listeners?: bool|array{ancestorScroll?: bool, ancestorResize?: bool, elementResize?: bool, layoutShift?: bool, animationFrame?: bool},
     * }
     */
    public function jsonSerialize(): array
    {
        return array_filter(get_object_vars($this), static fn(mixed $option): bool => $option !== null);
    }

    /**
     * @param list<string> $allowed
     */
    private function assertOneOf(string $name, mixed $value, array $allowed): void
    {
        if ($value === null || in_array($value, $allowed, strict: true)) {
            return;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'The positioning option "%s" must be one of %s, got "%s".',
                $name,
                implode(', ', $allowed),
                is_scalar($value) ? (string)$value : get_debug_type($value),
            ),
            1_788_400_004,
        );
    }

    /**
     * @param array<array-key, mixed>|null $value
     * @param list<string> $allowed
     */
    private function assertKeys(string $name, ?array $value, array $allowed): void
    {
        $unknown = array_diff(array_keys($value ?? []), $allowed);
        if ($unknown === []) {
            return;
        }

        throw new \InvalidArgumentException(
            sprintf(
                'The positioning option "%s" takes %s, got "%s".',
                $name,
                implode(', ', $allowed),
                implode('", "', array_map(strval(...), $unknown)),
            ),
            1_788_400_001,
        );
    }

    private static function invalid(int|string $name, string $expected): never
    {
        throw new \InvalidArgumentException(
            sprintf('The positioning option "%s" must be %s.', $name, $expected),
            1_788_400_005,
        );
    }
}
