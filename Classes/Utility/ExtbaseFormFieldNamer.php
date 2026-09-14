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

    /** @return list<string> */
    private function parseFieldPath(string $fieldName): array
    {
        $matches = null;
        preg_match_all('/([^.[\]]+)|\[(.*?)\]/', $fieldName, $matches, PREG_SET_ORDER);

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

    /** @param list<string> $fieldPath */
    private function stringifyFieldPathAsBrackets(array $fieldPath): string
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
}
