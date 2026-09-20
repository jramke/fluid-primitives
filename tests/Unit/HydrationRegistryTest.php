<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
use Jramke\FluidPrimitives\Registry\NestedComponentRegistry;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Page\AssetCollector;

#[AllowMockObjectsWithoutExpectations]
final class HydrationRegistryTest extends TestCase
{
    private ?string $capturedJs = null;
    private ?array $capturedAttributes = null;
    private AssetCollector $assetCollector;
    private HydrationRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capturedJs = null;
        $this->capturedAttributes = null;

        $this->assetCollector = $this->createMock(AssetCollector::class);
        $this->assetCollector
            ->method('addInlineJavaScript')
            ->willReturnCallback(function ($id, $js, $attributes, $options) {
                $this->capturedJs = $js;
                $this->capturedAttributes = $attributes;
                return $this->assetCollector;
            });

        $this->registry = new HydrationRegistry($this->assetCollector);
    }

    #[Test]
    public function storesAndRetrievesMultipleComponentTypes(): void
    {
        $this->registry->add('accordion', '«f1»', ['type' => 'accordion']);
        $this->registry->add('dialog', '«f2»', ['type' => 'dialog']);

        $all = $this->registry->getAll();

        $this->assertSame(['type' => 'accordion'], $all['accordion']['«f1»']);
        $this->assertSame(['type' => 'dialog'], $all['dialog']['«f2»']);
    }

    #[Test]
    public function clearsTheRegistry(): void
    {
        $this->registry->add('accordion', '«f1»', ['props' => []]);
        $this->registry->clear();

        $this->assertSame([], $this->registry->getAll());
    }

    #[Test]
    public function exposesDebugGlobalRegardlessOfApplicationContext(): void
    {
        // No request is bootstrapped in this unit test, so resolveGlobals() returns before the
        // locale lookup - 'debug' is set unconditionally before that, so it should be there either way.
        $this->assertArrayHasKey('debug', $this->registry->getGlobals());
        $this->assertIsBool($this->registry->getGlobals()['debug']);
    }

    #[Test]
    public function addsInlineJavaScriptWithComponentData(): void
    {
        $this->registry->add('accordion', '«f1»', [
            'controlled' => false,
            'props' => ['multiple' => true],
        ]);

        $this->assertStringContainsString('window.FluidPrimitives', $this->capturedJs);
        $this->assertStringContainsString('"accordion"', $this->capturedJs);
        $this->assertStringContainsString('"«f1»"', $this->capturedJs);
        $this->assertStringContainsString('"multiple":true', $this->capturedJs);
    }

    #[Test]
    public function includesNestedComponentsFromTheInjectedSiblingRegistryInTheInlineScript(): void
    {
        // Constructed directly (not via ::getInstance(), which needs a DI container this unit test
        // doesn't bootstrap) and wired in through the same constructor param HydrationRegistry's own
        // DI-resolved instance would receive in production - proving the two registries are meant
        // to be the *same* object, not proving anything about the container wiring itself.
        $nestedComponentRegistry = new NestedComponentRegistry();
        $nestedComponentRegistry->pushTrackingScope('field-array:«f0»:itemTemplate');
        $nestedComponentRegistry->recordNestedComponent('field', '«f1»');
        $nestedComponentRegistry->popTrackingScope();

        $registry = new HydrationRegistry($this->assetCollector, nestedComponentRegistry: $nestedComponentRegistry);
        $registry->add('field', '«f1»', ['props' => ['name' => 'firstName']]);

        $this->assertStringContainsString('nestedComponents', $this->capturedJs);
        $this->assertStringContainsString('field-array:«f0»:itemTemplate', $this->capturedJs);
    }
}
