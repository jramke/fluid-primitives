<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Utility\Typed;

// @mago-expect lint:cyclomatic-complexity
class SliderContext extends AbstractComponentContext
{
    /**
     * @return object{value:float, min:float, max:float, disabled:bool, style:string}
     */
    public function getThumbState(int $index, bool $disabled = false): object
    {
        $values = $this->resolveValues();
        $min = Typed::float($this->get('min'));
        $max = Typed::float($this->get('max'), 100.0);
        $gap = Typed::float($this->get('step'), 1.0) * Typed::float($this->get('minStepsBetweenThumbs'));
        $lastIndex = count($values) - 1;
        $value = $values[$index] ?? $min;
        $offset = $this->getOffset($this->getPercent($value));

        return (object)[
            'value' => $value,
            'min' => $index === 0 ? $min : ($values[$index - 1] ?? $min) + $gap,
            'max' => $index === $lastIndex ? $max : ($values[$index + 1] ?? $max) - $gap,
            'disabled' => $disabled || Typed::bool($this->get('disabled')),
            'style' => $this->isVertical()
                ? "position: absolute; bottom: {$offset}; transform: translateY(50%);"
                : "position: absolute; inset-inline-start: {$offset}; transform: translateX(-50%);",
        ];
    }

    /**
     * @param array{index?:int, name?:string|null, disabled?:bool} $thumb
     * @return object{name:?string, value:float, disabled:bool}
     */
    public function getHiddenInputState(array $thumb): object
    {
        $index = Typed::int($thumb['index'] ?? null);
        $name = Typed::stringOrNull($thumb['name'] ?? null);

        $values = $this->resolveValues();

        return (object)[
            'name' => $name ?? Typed::stringOrNull($this->get('name')),
            'value' => $values[$index] ?? Typed::float($this->get('min')),
            // a disabled slider or thumb leaves its value out of the form, like a native input
            'disabled' => Typed::bool($thumb['disabled'] ?? null) || Typed::bool($this->get('disabled')),
        ];
    }

    /**
     * @return object{state:string, disabled:bool, style:string, inRange:bool}
     */
    public function getMarkerState(float $value): object
    {
        $values = $this->resolveValues();
        $first = $values[0] ?? Typed::float($this->get('min'));
        $last = $values[count($values) - 1] ?? Typed::float($this->get('max'), 100.0);
        $percent = $this->getPercent($value);
        $offset = $this->getOffset($percent);
        [$from, $to] = $this->getFillSpan();

        return (object)[
            'state' => match (true) {
                $value < $first => 'under-value',
                $value > $last => 'over-value',
                default => 'at-value',
            },
            'disabled' => Typed::bool($this->get('disabled')),
            'style' => $this->isVertical()
                ? "position: absolute; pointer-events: none; bottom: {$offset}; translate: 0% 50%;"
                : "position: absolute; pointer-events: none; inset-inline-start: {$offset}; translate: -50% 0%;",
            'inRange' => $percent >= $from && $percent <= $to,
        ];
    }

    /**
     * The filled span in percent, as the machine's: between the thumbs of a range slider, else between
     * the thumb and the origin.
     *
     * @return array{float, float}
     */
    private function getFillSpan(): array
    {
        $percents = array_map($this->getPercent(...), $this->resolveValues());
        if (count($percents) > 1) {
            return [$percents[0], $percents[count($percents) - 1]];
        }

        $origin = match (Typed::stringOrNull($this->get('origin'))) {
            'end' => 100.0,
            'center' => 50.0,
            default => 0.0,
        };
        $thumb = $percents[0] ?? 0.0;

        return [min($thumb, $origin), max($thumb, $origin)];
    }

    /**
     * Plain-text representation of the current value(s), e.g. "40" for a single thumb or
     * "25 - 75" for a range - used as the `slider.valueText` part's server-rendered fallback, kept
     * live client-side by `Slider.ts`'s own render() once hydrated.
     */
    public function getValueText(): string
    {
        return implode(' - ', array_map($this->formatNumber(...), $this->resolveValues()));
    }

    /**
     * Mirrors the machine's offset of a thumb, a marker or an edge of the range. A contained thumb stays
     * inside the track, so it moves by up to half its own size. Only the client measures that size, so
     * the CSS reads `--slider-thumb-width` (or `-height`): the machine sets it once hydrated, a stylesheet
     * can set it before. `thumbSize` is the fallback. `$fromEnd` measures from the far end of the track.
     */
    private function getOffset(float $percent, bool $fromEnd = false): string
    {
        $edge = $this->formatNumber($fromEnd ? 100 - $percent : $percent);
        $factor = ($percent / 100) - 0.5;
        if (Typed::stringOrNull($this->get('thumbAlignment')) === 'center' || $factor === 0.0) {
            return "{$edge}%";
        }

        $dimension = $this->isVertical() ? 'height' : 'width';
        $thumbSize = Typed::arrayOrNull($this->get('thumbSize')) ?? [];
        $fallback = $this->formatNumber(Typed::float($thumbSize[$dimension] ?? null));
        $sign = $fromEnd ? '+' : '-';

        return "calc({$edge}% {$sign} var(--slider-thumb-{$dimension}, {$fallback}px) * {$this->formatNumber(
            $factor,
        )})";
    }

    /**
     * The range runs to the centers of the thumbs, so its edges move like them, except at the very ends
     * of the track.
     */
    public function getRangeStyle(): string
    {
        $edge = fn(float $percent, bool $fromEnd = false): string => $percent === 0.0 || $percent === 100.0
            ? $this->formatNumber($fromEnd ? 100 - $percent : $percent) . '%'
            : $this->getOffset($percent, $fromEnd);
        [$from, $to] = $this->getFillSpan();
        $start = $edge($from);
        $end = $edge($to, fromEnd: true);

        return $this->isVertical()
            ? "position: absolute; bottom: {$start}; top: {$end};"
            : "position: absolute; left: {$start}; right: {$end};";
    }

    private function getPercent(float $value): float
    {
        $min = Typed::float($this->get('min'));
        $max = Typed::float($this->get('max'), 100.0);

        return $max === $min ? 0.0 : (($value - $min) / ($max - $min)) * 100;
    }

    private function isVertical(): bool
    {
        return Typed::stringOrNull($this->get('orientation')) === 'vertical';
    }

    /**
     * `defaultValue` normalized to a plain `float[]` for the client - accepts a bare number
     * (`defaultValue="40"`) as well as a list (`defaultValue="{0: 25, 1: 75}"`), the same way
     * `SelectContext::getDefaultValue()` accepts a bare string alongside an array. Overrides the
     * raw `client="{true}"` prop of the same name (see `ClientPropsContextExtractor`).
     *
     * @return float[]|null
     */
    #[ExposeToClient(excludeIfNull: true)]
    public function getDefaultValue(): ?array
    {
        // `defaultValue` is declared `float|integer|float[]|integer[]`: a number or a list of them.
        // @mago-expect analysis:mixed-assignment
        $raw = $this->get('defaultValue');
        if ($raw === null || $raw === []) {
            return null;
        }

        return is_array($raw) ? array_map(Typed::float(...), $raw) : [Typed::float($raw)];
    }

    /**
     * @return float[]
     */
    private function resolveValues(): array
    {
        return $this->getDefaultValue() ?? [Typed::float($this->get('min'))];
    }

    /**
     * Rounds to avoid float noise (e.g. repeated division producing `33.333333333333336`) - PHP's
     * own float-to-string cast already drops trailing zeros (`50.0` becomes `"50"`), so nothing
     * further is needed to keep this clean.
     */
    private function formatNumber(float $value): string
    {
        return Typed::string(round($value, precision: 4));
    }
}
