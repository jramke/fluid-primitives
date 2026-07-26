<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Registry\HydrationRegistry;
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
}
