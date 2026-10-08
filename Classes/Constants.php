<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives;

class Constants
{
    public const PROP_ROOT_ID = 'rootId';

    public const GLOBAL_PROPS = ['ids', 'attributes', 'asChild', 'rootId', 'autoMount', 'spreadProps', 'class'];

    public const RESERVED_PROPS = [
        self::PROP_ROOT_ID, // reserved as we declare it manually
        'context', // reserved for the component context
        'component', // reserved for the component data
        'settings', // reserved for the component settings
        'class', // reserved for the component class and added automatically for every component
        'asChild',
        'spreadProps', // reserved and added automatically for every component, see AbstractComponentCollection::getComponentDefinition()
        'isRenderStencil', // reserved for ui:template's own stencil-detection flag on the context
    ];

    /**
     * Subset of RESERVED_PROPS that `ui:useProps` must strip when importing another component's
     * argument definitions, because they're tied to that specific render rather than being generic,
     * reusable configuration. `class`/`asChild` are deliberately NOT here - a wrapper built with
     * `ui:useProps` + `spreadProps` should keep inheriting them, including through any number of
     * further `ui:useProps` layers a userland component might stack on top.
     */
    public const NON_FORWARDABLE_PROPS = [self::PROP_ROOT_ID, 'context', 'component', 'settings'];

    public const COMPONENTS_THAT_SUPPORT_FIELD = [
        'checkbox',
        'checkbox-group',
        'combobox',
        'date-picker',
        'file-upload',
        'select',
        'input',
        'number-input',
        'radio-group',
        'slider',
        'switch',
        'textarea',
    ];

    public const MANUALLY_EXPOSED_TO_CLIENT_MARKER = '<!-- FLUID_PRIMITIVES_COMPONENT_MANUALLY_EXPOSED_TO_CLIENT -->';

    /**
     * Reserved boolean attribute {@see \Jramke\FluidPrimitives\ViewHelpers\AsChildViewHelper} renders
     * onto whichever tag it's placed on, so {@see \Jramke\FluidPrimitives\Service\Component\AsChildAttributeSpreader}
     * can find that exact tag in the rendered component HTML instead of assuming it's the first one.
     * Stripped before the merged attributes reach the final output.
     */
    public const AS_CHILD_TARGET_MARKER = 'data-fluid-primitives-aschild-target';
}
