<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * A ViewHelper that mimics the behavior of the popular `clsx` library for conditional class name merging.
 * It allows you to combine static class names with conditional ones based on the truthiness of values.
 *
 * It also helps you with whitespace management by filtering out empty or whitespace-only class names
 * and makes it possible to declare your class in multiple lines, which is especially useful in combination with Tailwind CSS.
 *
 * ## Examples
 *
 * A common pattern you maybe already needed to use is something like this:
 * ```html
 * <div class="my-class{f:if(condition: someCondition, then: ' my-other-class')}">
 * ```
 * This can get unwieldy when you have multiple conditional classes. Instead, you can use this ViewHelper:
 * ```html
 * <div class="{ui:cn(value: 'my-class', when: { 'my-other-class': someCondition})}">
 * ```
 *
 * In context of components you will do something like this:
 * ```html
 * <div class="{ui:cn(value: 'my-class-1 my-class-2 {class}')}">
 * ```
 * This will render the classes and if the component consumer passes a `class` prop, it will be appended.
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
            $renderingContext = $this->renderingContext ?? throw new \RuntimeException(
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
            return $value !== 0;
        }

        if (is_array($value)) {
            return count($value) > 0;
        }
        // Only null is falsy here; object/resource (the only remaining types) are always truthy in PHP.
        return !is_null($value);
    }
}
