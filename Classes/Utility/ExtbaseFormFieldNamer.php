<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * Builds Extbase-compatible bracketed field names (e.g. `tx_ext_plugin[conference][person][name]`)
 * from a plain field name, mirroring how `<f:form>`'s ViewHelpers derive submission names for the
 * Extbase property mapper.
 */
final readonly class ExtbaseFormFieldNamer
{
    /** Matches one path segment: either a bare run of non-delimiter chars, or bracket contents. */
    private const string FIELD_PATH_SEGMENT_PATTERN = '/([^.[\]]+)|\[(.*?)\]/';

    /**
     * Parses `$fieldName` (dot or bracket notation) and rebuilds it fully bracketed, with
     * `$fieldNamePrefix` and `$objectName` unshifted onto the front in that order.
     */
    public function prefixFieldName(string $fieldName, ?string $objectName, string $fieldNamePrefix): string
    {
        if ($fieldName === '') {
            return '';
        }

        $fieldPath = $this->parseFieldPath($fieldName);

        if (!in_array($objectName, [null, '', '0'], strict: true)) {
            array_unshift($fieldPath, $objectName);
        }

        if ($fieldNamePrefix !== '') {
            array_unshift($fieldPath, $fieldNamePrefix);
        }

        return $this->stringifyFieldPathAsBrackets($fieldPath);
    }

    /**
     * Splits a field name into its path segments, treating `.` and `[...]` as equivalent
     * delimiters - `person.country` and `person[country]` both parse to `['person', 'country']`.
     * An empty `[]` becomes an empty-string segment, the array-append marker.
     *
     * @return list<string>
     */
    public function parseFieldPath(string $fieldName): array
    {
        $matches = null;
        preg_match_all(self::FIELD_PATH_SEGMENT_PATTERN, $fieldName, $matches, PREG_SET_ORDER);

        $fieldPath = [];
        foreach ($matches as $match) {
            if (($match[1] ?? '') !== '') {
                $fieldPath[] = $match[1];
                continue;
            }

            $fieldPath[] = $match[2] ?? '';
        }

        return $fieldPath;
    }

    /**
     * Joins path segments back into one bracketed name: the first segment stays bare, every
     * segment after it gets wrapped in `[...]`, and an empty-string segment becomes `[]`.
     *
     * @param list<string> $fieldPath
     */
    public function stringifyFieldPathAsBrackets(array $fieldPath): string
    {
        $fieldName = '';

        foreach ($fieldPath as $segment) {
            if ($segment === '') {
                $fieldName .= '[]';
                continue;
            }

            $fieldName .= $fieldName === '' ? $segment : '[' . $segment . ']';
        }

        return $fieldName;
    }

    /**
     * Strips a trailing `[]` (the manual-bracket array-submission marker, e.g. `a11yNeeds[]`) if
     * present; returns the name unchanged otherwise.
     */
    public function trimArraySuffix(string $fieldName): string
    {
        return str_ends_with($fieldName, '[]') ? substr($fieldName, offset: 0, length: -2) : $fieldName;
    }
}
