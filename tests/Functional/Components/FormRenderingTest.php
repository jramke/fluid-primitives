<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\Components;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use Jramke\FluidPrimitives\Tests\Helper\TestEntity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Extbase\Security\HashScope;

final class FormRenderingTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // a form needs the plugin namespace of an Extbase request, see FormContext::getFieldNamePrefix()
        $this->setExtbasePlugin('Test', 'Form');
    }

    #[Test]
    public function rendersAFormElementWithMethodActionAndTheReadyState(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" method="get">
                <button type="submit">Send</button>
            </primitives:form.root>
        ');

        $form = $this->elementWith($html, 'data-form-root');
        $this->assertSame('form', $form->tag);
        $this->assertSame('get', $form->get('method'));
        $this->assertSame('/submit', $form->get('action'));
        // the library validates itself
        $this->assertTrue($form->has('novalidate'));
        // form state is driven client-side after hydration; the server always renders the initial one
        $this->assertSame('ready', $form->get('data-state'));
    }

    #[Test]
    public function throwsWithoutActionUriOutsideARealExtbaseRequest(): void
    {
        // Functional tests render outside a real Extbase MVC dispatch, so the rendering context's
        // ServerRequestInterface attribute is a plain PSR-7 request rather than an Extbase one -
        // exactly the condition production code must reject to build an action URI via the
        // UriBuilder. `actionUri` is the explicit escape hatch for exactly this case.
        $this->usePlainRequest();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('is not an Extbase RequestInterface');

        $this->renderTemplate('
            <primitives:form.root extensionName="Docs" pluginName="Form">
                <button type="submit">Send</button>
            </primitives:form.root>
        ');
    }

    #[Test]
    public function derivesThePrefixFromTheGivenPluginOutsideAnExtbaseRequest(): void
    {
        $this->usePlainRequest();

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" extensionName="Other" pluginName="Search">
                <button type="submit">Send</button>
            </primitives:form.root>
        ');

        $this->assertSame('tx_other_search', $this->hydratedFormProps()['fieldNamePrefix']);
        $this->assertStringContainsString('name="tx_other_search[__trustedProperties]"', $html);
    }

    #[Test]
    #[DataProvider('incompletePluginArguments')]
    public function throwsOutsideAnExtbaseRequestWithoutBothExtensionAndPluginName(string $arguments): void
    {
        $this->usePlainRequest();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine the field name prefix for <ui:form.root>');

        $this->renderTemplate(
            '<primitives:form.root actionUri="/submit" ' . $arguments . '>Send</primitives:form.root>',
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function incompletePluginArguments(): iterable
    {
        yield 'neither' => [''];
        yield 'only the extension' => ['extensionName="Other"'];
        yield 'only the plugin' => ['pluginName="Search"'];
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
        // the field names hashed into its value, which are. It carries the plugin's own field name prefix.
        $this->assertMatchesRegularExpression(
            '/name="tx_test_form\[__trustedProperties\]"[^>]*value="[^"]+"[^>]*>\s*<\/form>/',
            $html,
        );
    }

    #[Test]
    public function signsTheFieldNamesAndTheIdentitiesOfTheBoundObjectsIntoTheTrustedProperties(): void
    {
        $nested = new TestEntity();
        $nested->setTestUid(7);
        $entity = new TestEntity();
        $entity->setTestUid(42);
        $entity->setNested($nested);

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <primitives:field.root name="title">
                    <primitives:field.control asChild="{true}"><input type="text" /></primitives:field.control>
                </primitives:field.root>
                <primitives:field.root name="nested.title">
                    <primitives:field.control asChild="{true}"><input type="text" /></primitives:field.control>
                </primitives:field.root>
            </primitives:form.root>
        ', ['entity' => $entity]);

        // what Extbase's property mapper accepts back: the names of the fields and, for a bound
        // object that already exists, its identity - the nested one too
        $this->assertEquals(
            ['conference' => ['__identity' => 1, 'title' => 1, 'nested' => ['__identity' => 1, 'title' => 1]]],
            $this->trustedProperties($html),
        );
    }

    /**
     * @return iterable<string, array{0: ?int}>
     */
    public static function boundObjects(): iterable
    {
        yield 'a persisted object' => [42];
        yield 'a new object' => [null];
    }

    #[Test]
    #[DataProvider('boundObjects')]
    public function appendsAnIdentityFieldOnlyForAnObjectThatIsPersisted(?int $uid): void
    {
        $entity = new TestEntity();
        $entity->setTestUid($uid);

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <button type="submit">Send</button>
            </primitives:form.root>
        ', ['entity' => $entity]);

        $identityFields = array_filter(
            $this->elementsWith($html, 'name'),
            static fn($element): bool => $element->get('name') === 'tx_test_form[conference][__identity]',
        );

        if ($uid === null) {
            $this->assertSame([], $identityFields);
            // nor is it signed into the trusted properties: Extbase would expect it back
            $this->assertArrayNotHasKey('__identity', $this->trustedProperties($html)['conference'] ?? []);

            return;
        }

        $this->assertCount(1, $identityFields);
        $this->assertSame((string)$uid, array_values($identityFields)[0]->get('value'));
    }

    #[Test]
    public function rendersTheContentAndTheReadyIndicatorVisibleAndEveryStatusPartHidden(): void
    {
        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit">
                <primitives:form.content>
                    <button type="submit">Send</button>
                </primitives:form.content>
                <primitives:form.indicator state="{f:constant(name: \'Jramke\FluidPrimitives\Enum\FormState::Ready\')}">Ready</primitives:form.indicator>
                <primitives:form.indicator state="{f:constant(name: \'Jramke\FluidPrimitives\Enum\FormState::Submitting\')}">Sending</primitives:form.indicator>
                <primitives:form.successText>Saved!</primitives:form.successText>
                <primitives:form.errorText>Something went wrong.</primitives:form.errorText>
            </primitives:form.root>
        ');

        $this->assertFalse($this->elementWith($html, 'data-form-content')->has('hidden'));
        $this->assertTrue($this->elementWith($html, 'data-form-success-text')->has('hidden'));
        $this->assertTrue($this->elementWith($html, 'data-form-error-text')->has('hidden'));

        // Form.ts takes over from this ready state, and finds the indicators by their state as value
        $indicators = $this->elementsByValue($html, 'data-form-indicator');
        $this->assertSame(['ready', 'submitting'], array_keys($indicators));
        $this->assertFalse($indicators['ready']->has('hidden'));
        $this->assertTrue($indicators['submitting']->has('hidden'));
    }

    #[Test]
    public function populatesAFieldFromThePropertyPathOfTheBoundObjectInTheMarkupAndForTheClient(): void
    {
        // ComponentHydrationCollector used to register a root component's client-facing props from
        // the raw arguments as literally passed to its tag - frozen at that call site - rather than
        // from its own context, which FieldContext::beforeRendering() mutates via
        // ObjectAccess::getPropertyPath() for exactly this bound-object case. No `defaultValue`
        // argument is ever given here (it is meant to auto-resolve), so client hydration used to
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

        $this->assertSame('Existing title', $this->elementWith($html, 'data-field-control')->get('value'));

        $fieldRootId = $this->elementWith($html, 'data-field-root')->get('data-field-root');
        $registeredProps = HydrationRegistry::getInstance()->get('primitives', 'field', (string)$fieldRootId);
        $this->assertSame('Existing title', $registeredProps['props']['defaultValue'] ?? null);
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

        $this->assertSame('nested[title]', $this->elementWith($html, 'data-field-root')->get('data-name'));
        $this->assertSame('Nested title', $this->elementWith($html, 'data-field-control')->get('value'));
    }

    #[Test]
    public function readsTheDefaultValueOfAFieldNamedWithAnArraySuffixFromThePropertyWithoutTheSuffix(): void
    {
        // `name="title[]"` is the manual-bracket convention for a field that submits several values;
        // ObjectAccess has no concept of it and throws when it is left in the property path
        $entity = new TestEntity();
        $entity->setTitle('Existing title');

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <primitives:field.root name="title[]">
                    <primitives:field.control asChild="{true}">
                        <input type="text" />
                    </primitives:field.control>
                </primitives:field.root>
            </primitives:form.root>
        ', ['entity' => $entity]);

        $this->assertSame('title[]', $this->elementWith($html, 'data-field-root')->get('data-name'));
        $this->assertSame('Existing title', $this->elementWith($html, 'data-field-control')->get('value'));
    }

    #[Test]
    public function leavesTheNameOfAFieldAwareComponentUnprefixedBecauseTheClientPrefixesItOnPost(): void
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

        // The Form does not rewrite field names itself: `form.machine.ts` prefixes the plugin namespace and the
        // object name to every key it posts, so a name prefixed here as well would be prefixed twice.
        $this->assertSame('marketing', $this->elementWith($html, 'data-switch-hidden-input')->get('name'));
    }

    #[Test]
    public function signsTheRenamedPathOfAFieldInAFieldArrayRowNotItsRawName(): void
    {
        $entity = new TestEntity();
        $entity->setTestUid(42);

        $html = $this->renderTemplate('
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}">
                <primitives:fieldArray.root name="people" itemCount="2">
                    <primitives:fieldArray.itemGroup>
                        <f:for each="{0: 0, 1: 1}" as="index">
                            <primitives:fieldArray.item index="{index}">
                                <primitives:field.root name="firstName">
                                    <primitives:field.control asChild="{true}"><input type="text" /></primitives:field.control>
                                </primitives:field.root>
                            </primitives:fieldArray.item>
                        </f:for>
                    </primitives:fieldArray.itemGroup>
                    <primitives:fieldArray.itemTemplate>
                        <primitives:fieldArray.item>
                            <primitives:field.root name="firstName">
                                <primitives:field.control asChild="{true}"><input type="text" /></primitives:field.control>
                            </primitives:field.root>
                        </primitives:fieldArray.item>
                    </primitives:fieldArray.itemTemplate>
                </primitives:fieldArray.root>
            </primitives:form.root>
        ', ['entity' => $entity]);

        // The stencil of a row the client adds has no index to sign, and Extbase refuses an empty segment in the
        // middle of a name, so it is left out.
        $this->assertEquals(
            ['conference' => ['__identity' => 1, 'people' => [['firstName' => 1], ['firstName' => 1]]]],
            $this->trustedProperties($html),
        );
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function pluginNamespaces(): iterable
    {
        yield 'the plugin of the request' => ['', 'tx_docsext_registration'];
        yield 'an extension and plugin of the form' => [
            'extensionName="Other" pluginName="Search"',
            'tx_other_search',
        ];
    }

    #[Test]
    #[DataProvider('pluginNamespaces')]
    public function prefixesWhatExtbaseReadsBackWithTheNamespaceOfThePlugin(string $arguments, string $namespace): void
    {
        $this->setExtbasePlugin('DocsExt', 'Registration');
        $entity = new TestEntity();
        $entity->setTestUid(42);

        $html = $this->renderTemplate(
            '
            <primitives:form.root actionUri="/submit" objectName="conference" object="{entity}" ' .
            $arguments .
            '>
                <primitives:field.root name="title">
                    <primitives:field.control asChild="{true}"><input type="text" /></primitives:field.control>
                </primitives:field.root>
            </primitives:form.root>
        ',
            ['entity' => $entity],
        );

        // what the client prefixes every posted key with, and the object it posts them under
        $props = $this->hydratedFormProps();
        $this->assertSame($namespace, $props['fieldNamePrefix']);
        $this->assertSame('conference', $props['objectName']);
        $this->assertFalse($this->elementWith($html, 'data-form-root')->has('data-field-name-prefix'));

        $hiddenFields = [];
        foreach ($this->elementsWith($html, 'type') as $element) {
            if ($element->get('type') === 'hidden') {
                $hiddenFields[$element->get('name')] = $element->get('value');
            }
        }
        $this->assertSame($namespace . '[conference][__identity]', array_keys($hiddenFields)[0]);
        $this->assertSame('42', $hiddenFields[$namespace . '[conference][__identity]']);
        $this->assertArrayHasKey($namespace . '[__trustedProperties]', $hiddenFields);

        // signed below the namespace: Extbase looks the token up by the name of the argument
        $this->assertEquals(['conference' => ['__identity' => 1, 'title' => 1]], $this->trustedProperties($html));
    }

    #[Test]
    public function prefixesWithThePluginTheActionIsRegisteredUnderNotThePluginThatRenders(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['extbase']['extensions']['DocsExt']['plugins']['RegistrationSubmit'] = [
            'controllers' => [
                'Vendor\\DocsExt\\Controller\\RegistrationController' => [
                    'alias' => 'Registration',
                    'actions' => ['submit'],
                ],
            ],
        ];
        $this->setExtbasePlugin('DocsExt', 'Registration');

        $template = static fn(string $arguments): string => (
            '<primitives:form.root actionUri="/submit" controller="Registration" action="submit" ' .
            $arguments .
            '>Send</primitives:form.root>'
        );

        $this->renderTemplate($template(''));
        $this->assertSame('tx_docsext_registrationsubmit', $this->hydratedFormProps()['fieldNamePrefix']);

        $this->renderTemplate($template('pluginName="Registration"'));
        $this->assertSame('tx_docsext_registration', $this->hydratedFormProps()['fieldNamePrefix']);
    }

    /**
     * The client props of the form the last render hydrated.
     *
     * @return array<string, mixed>
     */
    private function hydratedFormProps(): array
    {
        $forms = HydrationRegistry::getInstance()->getAll()['primitives']['form'];

        return array_values($forms)[0]['props'];
    }

    /**
     * What Extbase reads back from the signed `__trustedProperties` field: the field names and identities
     * the form was rendered with.
     *
     * @return array<string, mixed>
     */
    private function trustedProperties(string $html): array
    {
        $token = null;
        foreach ($this->elementsWith($html, 'name') as $element) {
            if (preg_match('/(^|\[)__trustedProperties\]?$/', $element->get('name') ?? '') === 1) {
                $token = $element->get('value');
            }
        }
        $this->assertNotNull($token, 'Expected a __trustedProperties field.');

        $json = $this->get(HashService::class)->validateAndStripHmac(
            $token,
            HashScope::TrustedProperties->prefix(),
            HashAlgo::SHA3_256,
        );

        return json_decode((string)$json, true, flags: JSON_THROW_ON_ERROR);
    }
}
