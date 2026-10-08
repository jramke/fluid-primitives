<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

use Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity;
use TYPO3Fluid\Fluid\Core\Component\ComponentTemplateResolverInterface;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

/**
 * Everything about "is this component root" lives here, split by the two notions that actually
 * differ and the shape of input each is answered from:
 *
 * - **Declared root** - a template-shape fact, decided once at compile time from the filesystem
 *   ({@see isDeclaredRootFromViewHelperName()}), independent of how any specific call renders it.
 *   {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::isDeclaredRoot()} is the
 *   cached, per-collection reader of that same fact; {@see isDeclaredRootFromContext()} is the
 *   render-time readback for code that only has a `RenderingContextInterface`.
 * - **Rendered-as root** - a per-render fact, the declared one adjusted for `spreadProps`
 *   delegation. Computed once per render in
 *   {@see \Jramke\FluidPrimitives\Service\Component\ComponentIdentityResolver::resolve()} (not
 *   duplicated here - that adjustment is tightly coupled to the arguments already in scope there)
 *   and read back via {@see isRenderedAsRootFromContext()}.
 *
 * Both `...FromContext()` methods read the {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity}
 * {@see \Jramke\FluidPrimitives\Component\ComponentRenderer::createView()} stores in the
 * `ViewHelperVariableContainer` (under {@see ComponentIdentity::VHVC_KEY}) - deliberately not the
 * public `component` Fluid variable, which only ever carries
 * {@see ComponentIdentity::forView()}'s trimmed, template-facing subset.
 */
final class ComponentRootUtility
{
    /**
     * The compile-time-once rule for "is this a declared root component," given a bare
     * `$viewHelperName`: a single-segment name, or an explicit ".root" suffix (both resolved purely
     * from the string, no filesystem access), OR'd with a folder-shape default that subsumes them:
     * the resolved template file's own containing directory has no sibling
     * Root.html/Root.fluid.html file. This makes any standalone file in a Root-less folder root by
     * default (a flat folder of unrelated files like `Icon/Menu.html`, `Icon/Copy.html`, or a nested
     * folder like `CheckboxGroup/Examples/SelectAll.html`), matching the single-file-component
     * convention extended recursively to any depth, with no new syntax. $templateResolver is only
     * needed (and only ever consulted) to run the folder-shape check - pass the calling
     * `ComponentCollectionInterface` for a real decision; omit it to get just the classic,
     * filesystem-free rule (e.g. from a plain unit test with no real template tree).
     *
     * {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::getComponentDefinition()}
     * is the only place that runs this filesystem-aware branch - once per unique viewHelperName per
     * request, never at render time.
     * {@see \Jramke\FluidPrimitives\Component\AbstractComponentCollection::isDeclaredRoot()} reads
     * that already-decided fact back from the cached result instead of re-deriving it on every
     * render; {@see isDeclaredRootFromContext()} is the render-time readback of the same fact for
     * code that only has a `RenderingContextInterface`, not a `ComponentCollectionInterface`.
     */
    public static function isDeclaredRootFromViewHelperName(
        string $viewHelperName,
        ?ComponentTemplateResolverInterface $templateResolver = null,
    ): bool {
        if ($viewHelperName === '' || $viewHelperName === '0') {
            return false;
        }

        $componentParts = explode('.', $viewHelperName);
        if (count($componentParts) === 1) {
            return true; // Single part components are considered root components
        }

        if (strtolower($componentParts[1] ?? '') === 'root') {
            return true;
        }

        if (!$templateResolver instanceof ComponentTemplateResolverInterface) {
            return false;
        }

        $templateName = $templateResolver->resolveTemplateName($viewHelperName);
        $lastSlash = strrpos($templateName, needle: '/');
        $ownName = $lastSlash === false ? $templateName : substr($templateName, $lastSlash + 1);
        if (strtolower($ownName) === 'root') {
            // This file IS the directory's own designated Root file - a tiered name like
            // "molecules.checkboxGroup.root" reaches here because the check above only ever looks
            // at segment[1] specifically, not the *last* segment - and checking for a "Root sibling"
            // below would trivially find itself and wrongly conclude it isn't root.
            return true;
        }

        $directory = $lastSlash === false ? '' : substr($templateName, offset: 0, length: $lastSlash);
        $rootSiblingTemplateName = ($directory !== '' ? $directory . '/' : '') . 'Root';

        return (
            $templateResolver->getTemplatePaths()->resolveTemplateFileForControllerAndActionAndFormat(
                'Default',
                $rootSiblingTemplateName,
            ) === null
        );
    }

    /**
     * Reads back the already-decided, structural fact -
     * {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::$isDeclaredRoot} - for whichever
     * component is currently rendering. Deliberately the *unadjusted* fact, not the
     * `spreadProps`-adjusted rendered-as identity ({@see isRenderedAsRootFromContext()}) used for
     * render-time hydration/context bookkeeping - see that DTO property's own docblock for why:
     * this is what `PropViewHelper`/`ExposeToClientViewHelper` (validating whether `client`/`context`
     * may be declared at all) and
     * {@see \Jramke\FluidPrimitives\Utility\ComponentUtility::getRootIdFromContext()} need instead.
     */
    public static function isDeclaredRootFromContext(RenderingContextInterface $renderingContext): bool
    {
        $identity = self::currentIdentity($renderingContext);
        return $identity instanceof ComponentIdentity && $identity->isDeclaredRoot;
    }

    /**
     * This specific render's *rendered-as* root identity -
     * {@see \Jramke\FluidPrimitives\Domain\Dto\ComponentIdentity::$isRenderedAsRoot} read back the
     * same way {@see isDeclaredRootFromContext()} reads its declared counterpart. `false` whenever
     * this call forwards its hydration/context identity to an ancestor via `spreadProps` instead of
     * owning it. Lets `ExposeToClientViewHelper` tell "wrong template shape entirely" (not declared
     * root) apart from "right shape, but this call isn't the one responsible for hydration
     * bookkeeping" (declared root, not rendered as root).
     */
    public static function isRenderedAsRootFromContext(RenderingContextInterface $renderingContext): bool
    {
        $identity = self::currentIdentity($renderingContext);
        return $identity instanceof ComponentIdentity && $identity->isRenderedAsRoot;
    }

    /**
     * `null` outside any component, and also (deliberately) inside a `ui:template` stencil's
     * synthetic identity - `ui:template` only fakes the public `component` Fluid variable for
     * `ui:ref`/`ComponentNameUtility::...FromContext()`, not this `ViewHelperVariableContainer`
     * entry, since nothing that reads it (`ui:prop`, `ui:exposeToClient`) is ever legitimately used
     * from slot content in the first place - both `?? false` callers above already default to the
     * correct answer ("not root") for that case without `ui:template` needing to fake anything here.
     */
    private static function currentIdentity(RenderingContextInterface $renderingContext): ?ComponentIdentity
    {
        // ViewHelperVariableContainer::get() is Fluid core's own untyped API - inherently mixed,
        // narrowed immediately below via instanceof.
        // @mago-expect analysis:mixed-assignment
        $identity = $renderingContext->getViewHelperVariableContainer()->get(
            ComponentIdentity::class,
            ComponentIdentity::VHVC_KEY,
        );
        return $identity instanceof ComponentIdentity ? $identity : null;
    }
}
