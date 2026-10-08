<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Parses and converts component names: ViewHelper tag names (e.g. `Accordion.Item`), their
 * dashed-case component-name equivalent (`accordion.item`), and root/base/subcomponent name parts.
 */
class ComponentNameUtility
{
    public static function getComponentFullNameFromViewHelperName(string $viewHelperName): string
    {
        return self::camelCaseToLowerCaseDashed($viewHelperName);
    }

    /**
     * Returns the component's canonical camelCase base name/identity path (e.g. "fileUpload" for
     * both "FileUpload.Root" and "FileUpload.Item", or "molecules.checkboxGroup" for a tiered
     * `Molecules.CheckboxGroup.Root`) - this is the form used for context storage/lookup, the
     * `component.baseName` Fluid variable, and (dot-segments intact) the nested hydration registry
     * key. `$viewHelperName` itself is Fluid's own resolved dot-path, PascalCased per segment to
     * match the ViewHelper's PHP class name (e.g. "FileUpload.Root", not the lowercase-first
     * "fileUpload.root" a template author types) - `lcfirst()` per segment undoes just that leading
     * capital, the one part of the resolved name that isn't already what a template author would
     * write. Deliberately does *not* go through `getComponentFullNameFromViewHelperName()`'s
     * kebab-casing - use {@see camelCaseToLowerCaseDashed} on the result for the few things that
     * genuinely need kebab-case (ref attribute names, hydration keys).
     *
     * `$isDeclaredRoot` (the caller already knows or can state this - see
     * {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility::isDeclaredRootFromViewHelperName()})
     * decides how the call's own last segment is treated:
     *
     * - A subcomponent (`$isDeclaredRoot` false, e.g. `Accordion.Item`, `Molecules.CheckboxGroup.Label`)
     *   always shares its sibling root's identity - its own containing folder, i.e. everything but
     *   its own last segment.
     * - A classic `.Root` call (`Accordion.Root`, `Molecules.CheckboxGroup.Root`) drops that trailing
     *   marker segment the same way - it names the same folder its subcomponent siblings already
     *   resolve to, and adds nothing of its own.
     * - An independently-root leaf (folder-shape default - no `.Root`/subcomponent marker at all,
     *   e.g. `CheckboxGroupExamples.SelectAll`) has no marker to drop: its own last segment *is* its
     *   distinguishing identity, so the full path is kept. This is what keeps it from ever colliding
     *   with an unrelated real component sharing a leading segment - which is also why an example
     *   needing its own hydration bucket lives in its own sibling `<Component>Examples/` folder,
     *   never nested inside the real component's own folder (that folder's identity is already a
     *   leaf holding real instance records, not a namespace another component can nest under).
     *
     * Tiered components intentionally do *not* collapse their tier prefix away anymore - a `molecules`
     * tier is real, kept identity, not discarded, so `Molecules.CheckboxGroup.Root` and a
     * non-tiered `CheckboxGroup.Root` are deliberately different identities, never merged. Single-segment
     * names (`Clipboard`) have no other segment to drop or keep, so they're their own identity either way.
     */
    public static function getComponentBaseNameFromViewHelperName(string $viewHelperName, bool $isDeclaredRoot): string
    {
        $parts = explode('.', $viewHelperName);
        if (strtolower($parts[0]) === 'primitives') {
            array_shift($parts);
        }

        if (count($parts) === 1) {
            return lcfirst($parts[0]);
        }

        if ($isDeclaredRoot && strtolower($parts[count($parts) - 1]) !== 'root') {
            return implode('.', array_map(lcfirst(...), $parts));
        }

        array_pop($parts);
        return implode('.', array_map(lcfirst(...), $parts));
    }

    public static function getSubcomponentNameFromViewHelperName(string $viewHelperName): string
    {
        $fullName = self::getComponentFullNameFromViewHelperName($viewHelperName);
        $parts = explode('.', $fullName);
        if (count($parts) > 1) {
            return implode('.', array_slice($parts, offset: 1));
        }
        return '';
    }

    public static function getComponentFullNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $component = Typed::arrayOrNull($renderingContext->getVariableProvider()->get('component'));
        $fullName = $component !== null ? Typed::stringOrNull($component['fullName'] ?? null) : null;
        if ($fullName !== null) {
            return self::camelCaseToLowerCaseDashed($fullName);
        }
        return '';
    }

    /**
     * Reads the ambient `component.baseName` Fluid variable directly - already exactly
     * {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::$baseName} as computed once for
     * this component's own render (see `ComponentRenderer::createView()`), so there's nothing to
     * re-derive or re-parse here.
     */
    public static function getComponentBaseNameFromContext(RenderingContextInterface $renderingContext): string
    {
        $component = Typed::arrayOrNull($renderingContext->getVariableProvider()->get('component'));
        return $component !== null ? Typed::stringOrNull($component['baseName'] ?? null) ?? '' : '';
    }

    /**
     * The kebab-case form of {@see getComponentBaseNameFromContext} - for the few things that
     * genuinely need it (ref attribute names, hydration keys).
     * Deliberately still derives this from `component.baseName` via case conversion rather than
     * reading `component.clientBaseName` directly, even though a real render's `component` variable
     * (see {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::forView()}) carries both -
     * `baseName` alone is enough to answer this, so nothing that only sets `baseName` (a handwritten
     * test fixture, `ui:template`'s synthetic identity) needs to also remember to keep a second,
     * independently-derivable field in sync.
     */
    public static function getClientBaseNameFromContext(RenderingContextInterface $renderingContext): string
    {
        return self::camelCaseToLowerCaseDashed(self::getComponentBaseNameFromContext($renderingContext));
    }

    /**
     * The candidate root viewHelperNames a directory named `$entryName` (a component's own folder,
     * e.g. "Select") could resolve to, in the same precedence
     * {@see \Jramke\FluidPrimitives\Utility\ComponentRootUtility::isDeclaredRootFromViewHelperName()}
     * itself recognizes - a bare single-segment name (a single-file component with no sub-parts, e.g.
     * "clipboard" -> "Clipboard/Clipboard.*") or an explicit ".root" suffix (a component with its
     * own sub-parts, e.g. "select.root" -> "Select/Root.*"). Kept next to that method so both share
     * one definition of what makes a component root, rather than a caller re-deriving the same two
     * rules independently.
     *
     * @return array{0: string, 1: string}
     */
    public static function getRootViewHelperNameCandidates(string $entryName): array
    {
        $baseName = lcfirst($entryName);
        return [$baseName, "{$baseName}.root"];
    }

    public static function camelCaseToLowerCaseDashed(string $string): string
    {
        $result = GeneralUtility::camelCaseToLowerCaseUnderscored($string);
        return str_replace('_', replace: '-', subject: $result);
    }

    /**
     * Idempotent on already-camelCase input, unlike `GeneralUtility::underscoredToUpperCamelCase()`
     * (which lowercases the whole string first, destroying any capitalization a dash-less input -
     * i.e. already-camelCase - relied on to mark its word boundaries).
     */
    public static function lowerCaseDashedToCamelCase(string $string): string
    {
        if (!str_contains($string, '-')) {
            return lcfirst($string);
        }

        $segments = explode('-', $string);
        $firstSegment = array_shift($segments);
        return lcfirst($firstSegment) . implode('', array_map(ucfirst(...), $segments));
    }
}
