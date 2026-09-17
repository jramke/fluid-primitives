<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Contexts;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Utility\Typed;

class SliderContext extends AbstractComponentContext
{
    /**
     * @return object{value:float, min:float, max:float, disabled:bool}
     */
    public function getThumbState(int $index): object
    {
        $values = $this->resolveValues();
        $min = Typed::float($this->get('min'));
        $max = Typed::float($this->get('max'), 100.0);
        $gap = Typed::float($this->get('step'), 1.0) * Typed::float($this->get('minStepsBetweenThumbs'));
        $lastIndex = count($values) - 1;

        return (object)[
            'value' => $values[$index] ?? $min,
            'min' => $index === 0 ? $min : ($values[$index - 1] ?? $min) + $gap,
            'max' => $index === $lastIndex ? $max : ($values[$index + 1] ?? $max) - $gap,
            'disabled' => Typed::bool($this->get('disabled')),
        ];
    }

    /**
     * @param array{index?:int, name?:string|null} $thumb
     * @return object{name:?string, value:float}
     */
    public function getHiddenInputState(array $thumb): object
    {
        $index = Typed::int($thumb['index'] ?? null);
        $name = Typed::stringOrNull($thumb['name'] ?? null);

        $values = $this->resolveValues();

        return (object)[
            'name' => $name ?? Typed::stringOrNull($this->get('name')),
            'value' => $values[$index] ?? Typed::float($this->get('min')),
        ];
    }

    /**
     * @return object{state:string, disabled:bool}
     */
    public function getMarkerState(float $value): object
    {
        $values = $this->resolveValues();
        $first = $values[0] ?? Typed::float($this->get('min'));
        $last = $values[count($values) - 1] ?? Typed::float($this->get('max'), 100.0);

        return (object)[
            'state' => match (true) {
                $value < $first => 'under-value',
                $value > $last => 'over-value',
                default => 'at-value',
            },
            'disabled' => Typed::bool($this->get('disabled')),
        ];
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
     * Mirrors the machine's own `getVisibility()`: before hydration the thumb size hasn't been
     * measured yet, so anything but "center" alignment must render invisible to avoid a flash at
     * the wrong position/size once the real, measured offset is applied.
     */
    public function isUnmeasured(): bool
    {
        return Typed::stringOrNull($this->get('thumbAlignment')) !== 'center';
    }

    /**
     * The range "fill" position is derived purely from `defaultValue`/`min`/`max`/`origin` - unlike
     * the thumb/marker offsets, it needs no live thumb-size measurement, so it can be precomputed
     * here instead of leaving the range bar empty until hydration.
     */
    public function getRangeStyle(): string
    {
        $min = Typed::float($this->get('min'));
        $max = Typed::float($this->get('max'), 100.0);

        $percent = array_map(static fn(float $value): float => $max === $min
            ? 0.0
            : (($value - $min) / ($max - $min)) * 100, $this->resolveValues());
        $first = $percent[0] ?? 0.0;
        $last = $percent[count($percent) - 1] ?? 0.0;

        [$start, $end] = match (true) {
            count($percent) > 1 => ["{$this->formatNumber($first)}%", "{$this->formatNumber(100 - $last)}%"],
            Typed::stringOrNull($this->get('origin')) === 'center' => $first < 50
                ? ["{$this->formatNumber($first)}%", '50%']
                : ['50%', "{$this->formatNumber(100 - $first)}%"],
            Typed::stringOrNull($this->get('origin')) === 'end' => ["{$this->formatNumber($last)}%", '0%'],
            default => ['0%', "{$this->formatNumber(100 - $last)}%"],
        };

        return Typed::stringOrNull($this->get('orientation')) === 'vertical'
            ? "position: absolute; bottom: {$start}; top: {$end};"
            : "position: absolute; left: {$start}; right: {$end};";
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
