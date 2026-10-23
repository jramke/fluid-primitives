<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Fixtures\UsePropsForwardingComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\InvalidArgumentValueException;

final class UsePropsViewHelperTest extends FunctionalTestCase
{
    /**
     * Hydration now requires a component's collection to be globally registered (see
     * ComponentIdentityResolver::resolve()/ComponentHydrationCollector::collectForRootComponent()) -
     * registering it only on this one view's own resolver (still needed for ViewHelper resolution
     * itself) is no longer enough on its own for the namespace-identifier reverse lookup to succeed.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['wrapper'] = [
            UsePropsForwardingComponentCollection::class,
        ];
        $this
            ->getView()
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('wrapper', new UsePropsForwardingComponentCollection());
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['wrapper']);
        parent::tearDown();
    }

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
        $html = $this->renderTemplate('
            <wrapper:field.root rootId="test-root" name="username">
                <wrapper:field.control asChild="{true}" class="extra-class">
                    <input type="text" name="username" />
                </wrapper:field.control>
            </wrapper:field.root>
        ');

        // asChild worked: the primitive rendered the consumer's own <input> instead of throwing its
        // "asChild is required" error, merged its own ref attribute onto it, and the class that was
        // forwarded lands on the same element.
        $input = $this->elementWith($html, 'data-field-control');
        $this->assertSame('input', $input->tag);
        $this->assertSame('test-root', $input->get('data-field-control'));
        $this->assertSame('extra-class', $input->get('class'));
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
        $html = $this->renderTemplate('
            <primitives:menu.root>
                <primitives:menu.positioner>
                    <primitives:menu.content>
                        <wrapper:menu.checkboxItem value="bold" checked="{true}">Bold</wrapper:menu.checkboxItem>
                    </primitives:menu.content>
                </primitives:menu.positioner>
            </primitives:menu.root>
        ');

        $item = $this->elementsByValue($html, 'data-menu-item')['bold'];
        $this->assertSame('true', $item->get('aria-checked'));
        $this->assertSame('checked', $item->get('data-state'));
    }

    #[Test]
    public function asBindingMakesAdditionalArgumentsAllowedWhenTheImportedComponentAllowsThem(): void
    {
        // DelegatesToAllowsExtra.root genuinely delegates (ui:useProps ... as="rootProps" paired
        // with spreadProps="{rootProps}") to AllowsExtra.root, which calls ui:attributes() itself -
        // an unknown argument passed to the wrapper should reach all the way through to it.
        $html = $this->renderTemplate('
            <wrapper:delegatesToAllowsExtra.root data-testid="passthrough">Content</wrapper:delegatesToAllowsExtra.root>
        ');

        $this->assertStringContainsString('data-testid="passthrough"', $html);
        // The delegate tag's own explicit variant="hardcoded-in-wrapper" wins over whatever the
        // wrapper's caller would otherwise forward - isExplicitlyProvidedAtCallSite() staying
        // correct through the simplified resolveSpreadProps().
        $this->assertStringContainsString('data-variant="hardcoded-in-wrapper"', $html);
    }

    #[Test]
    public function escapesAnAttributeForwardedThroughSpreadPropsOnlyOnce(): void
    {
        $value = 'Tom & "Jerry" <b>';

        $html = $this->renderTemplate('<wrapper:delegatesToAllowsExtra.root title="{value}">Content</wrapper:delegatesToAllowsExtra.root>', [
            'value' => $value,
        ]);

        $this->assertSame($value, $this->elementWith($html, 'data-variant')->get('title'));
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    #[Test]
    public function usePropsWithoutAsNeverGrantsAdditionalArguments(): void
    {
        // Regression test for the bug an earlier draft's naive fix introduced: ImportsOnlyFromAllowsExtra.root
        // imports AllowsExtra.root's prop shape (which allows additional arguments) but never
        // delegates rendering to it - no `as=`, no spreadProps anywhere. An unknown argument must
        // still be rejected, exactly like Dialog/Header.fluid.html/Footer.fluid.html.
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Invalid arguments supplied to component');

        $this->renderTemplate('
            <wrapper:importsOnlyFromAllowsExtra.root data-testid="should-not-be-allowed">Content</wrapper:importsOnlyFromAllowsExtra.root>
        ');
    }

    #[Test]
    public function chainedAsDelegationPropagatesAdditionalArgumentsAllowed(): void
    {
        // DelegatesToDelegatesToAllowsExtra.root delegates (via its own as=) to DelegatesToAllowsExtra.root,
        // which itself delegates to AllowsExtra.root - additionalArgumentsAllowed must propagate
        // through both hops, not just the first, proving the generalization beyond primitives:-only
        // imports actually composes.
        $html = $this->renderTemplate('
            <wrapper:delegatesToDelegatesToAllowsExtra.root data-testid="passthrough">Content</wrapper:delegatesToDelegatesToAllowsExtra.root>
        ');

        $this->assertStringContainsString('data-testid="passthrough"', $html);
    }

    #[Test]
    public function usePropsWithoutAsDoesNotInheritADelegatingImportsAdditionalArguments(): void
    {
        // The InternalBindingAnnotation regression test: ImportsOnlyFromDelegatesToAllowsExtra.root
        // imports (no as=, pure declaration reuse) from DelegatesToAllowsExtra.root, which itself
        // used as= internally to delegate. Without InternalBindingAnnotation excluding that internal
        // "rootProps" binding from PropsUtility::cleanupNonForwardableProps(), it would leak through
        // as if it were a real, forwardable prop and incorrectly grant additionalArgumentsAllowed here.
        $this->expectException(InvalidArgumentValueException::class);
        $this->expectExceptionMessage('Invalid arguments supplied to component');

        $this->renderTemplate('
            <wrapper:importsOnlyFromDelegatesToAllowsExtra.root data-testid="should-not-be-allowed">Content</wrapper:importsOnlyFromDelegatesToAllowsExtra.root>
        ');
    }

    #[Test]
    public function asBindingDoesNotRegisterASpuriousSecondRootForTheDelegatedRender(): void
    {
        // The one failure mode ComponentIdentityResolver's broadened spreadProps truthy-check could
        // get wrong is silent, not a thrown error: an array-valued spreadProps must still suppress
        // the delegated render from becoming its own competing root. Field.Control delegates to
        // primitives:field.control the same as=-bound way - exactly one rootId should ever be
        // registered for it, not a second one generated by the inner delegated render.
        $this->renderTemplate('
            <wrapper:field.root rootId="as-binding-root" name="username">
                <wrapper:field.control asChild="{true}">
                    <input type="text" name="username" />
                </wrapper:field.control>
            </wrapper:field.root>
        ');

        // a spurious inner root would register under the namespace of the inner collection (`primitives`)
        $hydrationData = HydrationRegistry::getInstance()->getAll();
        $this->assertSame(['wrapper'], array_keys($hydrationData));
        $this->assertSame(['field'], array_keys($hydrationData['wrapper']));
        $this->assertSame(['as-binding-root'], array_keys($hydrationData['wrapper']['field']));
    }
}
