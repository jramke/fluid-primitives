<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 *
 * Combines fixed class names with class names that apply on a condition.
 *
 * ```html
 * <article class="{ui:cn(value: 'rounded border p-4', when: {'opacity-50': isSoldOut, 'border-red-500 bg-red-50': hasError})}">
 * ```
 *
 * Each key in `when` holds one or more class names and applies when its value is truthy. Empty entries and duplicates are dropped, so a long class list can span several lines, which suits Tailwind CSS.
 *
 * In a component, add the classes a caller passes to your own:
 *
 * ```html
 * <div class="{ui:cn(value: 'rounded border p-4 {class}')}">
 * ```
 *
 */
class CnViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('value', 'string', 'The class input string to process');
        $this->registerArgument(
            'when',
            'array',
            'Array of conditional classes where key is class(es) and value is condition',
            false,
            [],
        );
        $this->registerArgument('as', 'string', 'Variable name to assign the result to', false, '');
    }

    public function getContentArgumentName(): string
    {
        return 'value';
    }

    public function render(): string
    {
        $classes = [];

        $classesString = trim(Typed::string($this->renderChildren()));
        if ($classesString !== '') {
            $classes = array_merge($classes, $this->parseClassString($classesString));
        }

        $whenArray = Typed::arrayOrNull($this->arguments['when']) ?? [];
        if ($whenArray !== []) {
            $classes = array_merge($classes, $this->processWhenArray($whenArray));
        }

        $classes = array_filter(
            array_unique($classes),
            static fn(string $class) => !in_array(trim($class), ['', '0'], strict: true),
        );

        $as = Typed::string($this->arguments['as']);
        if ($as !== '') {
            $renderingContext = $this->renderingContext ?? throw new \LogicException(
                'Cn ViewHelper is missing its rendering context.',
                1_788_100_011,
            );
            $renderingContext->getVariableProvider()->add($as, implode(' ', $classes));
            return '';
        }

        return implode(' ', $classes);
    }

    /**
     * Process when array - handles conditional classes where key is class(es) and value is condition
     * Supports multiple classes per condition by allowing space-separated class strings as keys
     *
     * @return string[]
     */
    private function processWhenArray(array $whenArray): array
    {
        $classes = [];

        // $value is genuinely mixed by design - isTruthy() below is a generic condition-value check,
        // and the indexed-array branch narrows it explicitly with Typed::string() itself.
        // @mago-expect analysis:mixed-assignment
        foreach ($whenArray as $key => $value) {
            if (is_int($key)) {
                // Indexed array: treat value as class name(s)
                $value = Typed::string($value);
                if ($value !== '') {
                    $classes = array_merge($classes, $this->parseClassString($value));
                }
                continue;
            }

            if ($this->isTruthy($value)) {
                // Associative array: key is class name(s), value is condition
                // This supports multiple classes per condition like: 'btn-primary btn-large': '{condition}'
                $classes = array_merge($classes, $this->parseClassString($key));
            }
        }

        return $classes;
    }

    /**
     * @return string[]
     */
    private function parseClassString(string $classString): array
    {
        if (in_array(trim($classString), ['', '0'], strict: true)) {
            return [];
        }

        // Split by whitespace and filter out empty values
        return array_filter(
            preg_split('/\s+/', trim($classString)) ?: [],
            static fn($class) => !in_array(trim($class), ['', '0'], strict: true),
        );
    }

    /**
     * Check if a value is truthy in the context of class conditions
     * This mimics JavaScript's truthy evaluation for the clsx library
     */
    private function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            // Handle common falsy string representations
            return !in_array($lower, ['', '0', 'false', 'no', 'null', 'undefined'], strict: true);
        }

        if (is_numeric($value)) {
            return (float)$value !== 0.0;
        }

        if (is_array($value)) {
            return count($value) > 0;
        }
        // Only null is falsy here; object/resource (the only remaining types) are always truthy in PHP.
        return !is_null($value);
    }
}
