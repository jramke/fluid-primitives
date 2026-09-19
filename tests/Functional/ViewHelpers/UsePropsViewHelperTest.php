<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\Fixtures\UsePropsForwardingComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class UsePropsViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function forwardsAsChildAndClassThroughAThinWrapperThatDelegatesViaSpreadProps(): void
    {
        // Regression test: field.control's asChild argument (added by
        // AbstractComponentCollection::getComponentDefinition() because its own template calls
        // ui:ref) used to be silently dropped by any thin ui:useProps + spreadProps wrapper around
        // it - the exact shape every Registry/docs component wrapper uses - because ui:useProps
        // stripped asChild/class as "reserved" and nothing re-added them for a wrapper whose own
        // template text never mentions ui:ref/class itself. This isn't specific to `primitives:`
        // components - the fixture collection registered below stands in for any userland collection
        // built the same way.
        $view = $this->getView();
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('wrapper', new UsePropsForwardingComponentCollection());

        $html = $this->renderTemplate('
            <wrapper:field.root rootId="test-root" name="username">
                <wrapper:field.control asChild="{true}" class="extra-class">
                    <input type="text" name="username" />
                </wrapper:field.control>
            </wrapper:field.root>
        ');

        // asChild worked: the primitive rendered the consumer's own <input> instead of throwing its
        // "asChild is required" error, and merged its own ref/data attributes onto it.
        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('data-scope="field"', $html);
        $this->assertStringContainsString('data-part="control"', $html);
        $this->assertStringContainsString('name="username"', $html);

        // class was forwarded and merged onto the same element too.
        $this->assertStringContainsString('extra-class', $html);
    }

    #[Test]
    public function forwardsANonNullDefaultBooleanPropThroughSpreadProps(): void
    {
        // Regression test: resolveSpreadProps() used `??=` to decide whether a forwarded prop still
        // needed pulling from the parent scope. That only works when an unset argument is truly
        // absent from $arguments. On Fluid's uncached (interpreted) render path - which every
        // template hits the first time it's rendered in a process, before TemplateCompiler has a
        // cached class for it - TYPO3Fluid's ViewHelperInvoker pre-fills every declared-but-omitted
        // argument with its ArgumentDefinition default before the component ever sees it. For
        // menu.checkboxItem's `checked` prop (default false), that pre-filled `false` was
        // indistinguishable from "not passed", so `??=` refused to overwrite it with the wrapper's
        // own `checked="{true}"` - silently rendering an explicitly-checked item as unchecked.
        $view = $this->getView();
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('wrapper', new UsePropsForwardingComponentCollection());

        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <wrapper:menu.checkboxItem value="bold" checked="{true}">Bold</wrapper:menu.checkboxItem>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $this->assertStringContainsString('aria-checked="true"', $html);
        $this->assertStringContainsString('data-state="checked"', $html);
    }
}
