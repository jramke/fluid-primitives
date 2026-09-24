<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use Jramke\FluidPrimitives\Tests\Helper\TestEntity;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class FormRenderingTest extends FunctionalTestCase
{
    #[Test]
    public function rendersFormElementWithMethodAndAction(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" method="get">
                <button type="submit">Send</button>
            </primitives:form.root>
        ');

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="get"', $html);
        $this->assertStringContainsString('action="/submit"', $html);
        $this->assertStringContainsString('novalidate', $html);
    }

    #[Test]
    public function throwsWithoutActionUriOutsideARealExtbaseRequest(): void
    {
        // Functional tests render outside a real Extbase MVC dispatch, so the rendering context's
        // ServerRequestInterface attribute is a plain PSR-7 request rather than an Extbase one -
        // exactly the condition production code must reject to build an action URI via the
        // UriBuilder. `actionUri` is the explicit escape hatch for exactly this case.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not an Extbase RequestInterface');

        $this->renderTemplate('
            <primitives:form.root>
                <button type="submit">Send</button>
            </primitives:form.root>
        ');
    }

    #[Test]
    public function appendsTrustedPropertiesFieldBeforeClosingFormTag(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <button type="submit">Send</button>
            </primitives:form.root>
        ');

        // The `__trustedProperties` field itself is deliberately not objectName-prefixed (mirroring
        // core's `<f:form>` - Extbase always reads it back from this fixed top-level key), unlike
        // the field names hashed into its value, which are. It would still carry the plugin's own
        // field name prefix in a real request; this functional test environment has none.
        $this->assertMatchesRegularExpression('/name="__trustedProperties"[^>]*value="[^"]+"[^>]*>\s*<\/form>/', $html);
    }

    #[Test]
    public function appendsIdentityFieldForAPersistedBoundObject(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <button type="submit">Send</button>
            </primitives:form.root>
        ', ['entity' => $entity]);

        $this->assertStringContainsString('name="conference[__identity]" value="42"', $html);
    }

    #[Test]
    public function omitsIdentityFieldForANewUnpersistedObject(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <button type="submit">Send</button>
            </primitives:form.root>
        ', ['entity' => new TestEntity()]);

        $this->assertStringNotContainsString('__identity', $html);
    }

    #[Test]
    public function rendersReadyStateWithContentVisibleAndIndicatorsHidden(): void
    {
        // Form state (submitting/error/success) is driven client-side after hydration; the server
        // always renders the initial "ready" state.
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit">
                <primitives:form.content>
                    <button type="submit">Send</button>
                </primitives:form.content>
                <primitives:form.successText>Saved!</primitives:form.successText>
                <primitives:form.errorText>Something went wrong.</primitives:form.errorText>
            </primitives:form.root>
        ');

        $this->assertDoesNotMatchRegularExpression('/data-part="content"[^>]*hidden/', $html);
        $this->assertMatchesRegularExpression('/hidden[^>]*data-part="success-text"/', $html);
        $this->assertMatchesRegularExpression('/hidden[^>]*data-part="error-text"/', $html);
    }

    #[Test]
    public function populatesNestedFieldDefaultValueFromTheBoundObjectsPropertyPath(): void
    {
        $entity = new TestEntity();
        $entity->setTitle('Existing title');

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <primitives:field.root name="title">
                    <primitives:field.control asChild="{true}">
                        <input type="text" />
                    </primitives:field.control>
                </primitives:field.root>
            </primitives:form.root>
        ', ['entity' => $entity]);

        $this->assertStringContainsString('value="Existing title"', $html);
    }

    #[Test]
    public function canonicalizesADotNotationNameToBracketsWhileStillResolvingDefaultValueFromTheDottedPath(): void
    {
        // FieldContext::beforeRendering() must resolve `defaultValue` from the *original*
        // dot-notation name (ObjectAccess::getPropertyPath() has no concept of brackets) before
        // canonicalizing `name` itself to the bracket wire format Extbase's own argument mapping
        // requires - a regression here would silently break either nested error matching
        // (person.country vs person[country], see form.path.ts) or nested defaultValue resolution.
        $nested = new TestEntity();
        $nested->setTitle('Nested title');
        $entity = new TestEntity();
        $entity->setNested($nested);

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <primitives:field.root name="nested.title">
                    <primitives:field.control asChild="{true}">
                        <input type="text" />
                    </primitives:field.control>
                </primitives:field.root>
            </primitives:form.root>
        ', ['entity' => $entity]);

        $this->assertStringContainsString('data-name="nested[title]"', $html);
        $this->assertStringContainsString('value="Nested title"', $html);
    }

    #[Test]
    public function registersTheBoundObjectsResolvedDefaultValueForClientHydrationTooNotJustTheServerRenderedHtml(): void
    {
        // ComponentHydrationCollector used to register a root component's client-facing props from
        // the raw arguments as literally passed to its tag - frozen at that call site - rather than
        // from its own context, which FieldContext::beforeRendering() (above) mutates via
        // ObjectAccess::getPropertyPath() for exactly this bound-object case. No `defaultValue`
        // argument is ever given here (it's meant to auto-resolve), so client hydration used to
        // register it as entirely absent regardless of what the server-rendered HTML showed.
        $entity = new TestEntity();
        $entity->setTitle('Existing title');

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <primitives:field.root name="title">
                    <primitives:field.control asChild="{true}">
                        <input type="text" />
                    </primitives:field.control>
                </primitives:field.root>
            </primitives:form.root>
        ', ['entity' => $entity]);

        preg_match('/id="field:([^"]*)" data-scope="field" data-part="root"/', $html, $fieldMatches);
        $fieldRootId = $fieldMatches[1] ?? null;
        $this->assertNotNull($fieldRootId);

        $registeredProps = HydrationRegistry::getInstance()->get('primitives', 'field', $fieldRootId);

        $this->assertSame('Existing title', $registeredProps['props']['defaultValue'] ?? null);
    }

    #[Test]
    public function prefixesNestedFieldAwareComponentNameWithTheFormsObjectName(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference">
                <primitives:field.root name="marketing">
                    <primitives:switch.root>
                        <primitives:switch.control>
                            <primitives:switch.thumb />
                        </primitives:switch.control>
                        <primitives:switch.hiddenInput />
                    </primitives:switch.root>
                </primitives:field.root>
            </primitives:form.root>
        ');

        // The Form does not rewrite field names itself (name-prefixing for actual Extbase argument
        // mapping only happens for `__trustedProperties`/`__identity`), so the Switch keeps using
        // whatever plain `name` the Field passed through unprefixed.
        $this->assertStringContainsString('name="marketing"', $html);
    }
}
