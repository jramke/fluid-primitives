<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Fixtures\RootDetectionComponentCollection;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentRootUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ComponentRootUtilityTest extends TestCase
{
    /**
     * Against {@see RootDetectionComponentCollection}, which has every folder shape: classic Root + parts
     * (Widget/), a nested example folder without a Root file (Widget/Examples/), a flat folder where no file
     * is named like the folder (Icon/), and a tiered Root file (Tier/Widget/Root).
     *
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function viewHelperNames(): iterable
    {
        yield 'a single segment is its own root' => ['collapsible', true];
        yield 'a ".root" suffix names the root' => ['widget.root', true];
        yield 'a part next to a Root file is no root' => ['widget.item', false];
        yield 'a nested file in a folder without a Root file' => ['widget.examples.demo', true];
        yield 'a file in a flat folder without a Root file' => ['icon.menu', true];
        yield 'another file of that flat folder' => ['icon.copy', true];
        // used to be missed: the classic rule only ever looked at the second segment
        yield 'a tiered Root file' => ['tier.widget.root', true];
    }

    #[Test]
    #[DataProvider('viewHelperNames')]
    public function detectsRootComponentsByNameAndByTheFolderShapeOfTheirTemplates(
        string $viewHelperName,
        bool $expected,
    ): void {
        $this->assertSame($expected, ComponentRootUtility::isDeclaredRootFromViewHelperName(
            $viewHelperName,
            new RootDetectionComponentCollection(),
        ));
    }
}
