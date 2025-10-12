<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\ViewHelpers;

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
        $this->registerArgument('when', 'array', 'Array of conditional classes where key is class(es) and value is condition');
        $this->registerArgument('as', 'string', 'Variable name to assign the result to');
    }

    public function getContentArgumentName(): string
    {
        return 'value';
    }

    public function render(): string
    {
        $classes = [];

        $classesString = $this->renderChildren() ?? '';
        if (!empty($classesString)) {
            $classes = array_merge($classes, $this->parseClassString((string)$classesString));
        }

        $whenArray = $this->arguments['when'] ?? [];
        if (!empty($whenArray)) {
            $classes = array_merge($classes, $this->processWhenArray($whenArray));
        }

        $classes = array_filter(array_unique($classes), function ($class) {
            return !empty(trim($class)) && is_string($class);
        });

        $as = $this->arguments['as'] ?? '';

        if (!empty($as)) {
            $this->renderingContext->getVariableProvider()->add($as, implode(' ', $classes));
            return '';
        } else {
            return implode(' ', $classes);
        }
    }

    /**
     * Process when array - handles conditional classes where key is class(es) and value is condition
     * Supports multiple classes per condition by allowing space-separated class strings as keys
     */
    private function processWhenArray(array $whenArray): array
    {
        $classes = [];

        foreach ($whenArray as $key => $value) {
            if (is_int($key)) {
                // Indexed array: treat value as class name(s)
                if (!empty($value)) {
                    $classes = array_merge($classes, $this->parseClassString((string)$value));
                }
            } else {
                // Associative array: key is class name(s), value is condition
                // This supports multiple classes per condition like: 'btn-primary btn-large': '{condition}'
                if ($this->isTruthy($value)) {
                    $classes = array_merge($classes, $this->parseClassString($key));
                }
            }
        }

        return $classes;
    }

    private function parseClassString(string $classString): array
    {
        if (empty(trim($classString))) {
            return [];
        }

        // Split by whitespace and filter out empty values
        return array_filter(
            preg_split('/\s+/', trim($classString)),
            function ($class) {
                return !empty(trim($class));
            }
        );
    }

    /**
     * Check if a value is truthy in the context of class conditions
     * This mimics JavaScript's truthy evaluation for the clsx library
     */
    private function isTruthy($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $lower = strtolower(trim($value));
            // Handle common falsy string representations
            return $lower !== '' && $lower !== '0' && $lower !== 'false' && $lower !== 'no' && $lower !== 'null' && $lower !== 'undefined';
        }

        if (is_numeric($value)) {
            return $value != 0;
        }

        if (is_array($value)) {
            return count($value) > 0;
        }

        if (is_null($value)) {
            return false;
        }

        return !empty($value);
    }
}
