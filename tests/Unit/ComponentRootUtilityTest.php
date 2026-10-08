<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\Fixtures\RootDetectionComponentCollection;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentRootUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentRootUtilityTest extends TestCase
{
    #[Test]
    public function returnsTrueForSinglePartComponentName(): void
    {
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('Collapsible'));
    }

    #[Test]
    public function returnsTrueWhenSecondPartIsRoot(): void
    {
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('Accordion.Root'));
    }

    #[Test]
    public function returnsFalseForItemComponents(): void
    {
        $this->assertFalse(ComponentRootUtility::isDeclaredRootFromViewHelperName('Accordion.Item'));
    }

    #[Test]
    public function handlesPrimitivesNamespaceSecondPartIsNotRoot(): void
    {
        $this->assertFalse(ComponentRootUtility::isDeclaredRootFromViewHelperName('Primitives.Dialog.Root'));
    }

    #[Test]
    public function fallsBackToTheClassicRuleWithoutATemplateResolver(): void
    {
        // No $templateResolver given - the folder-shape default never runs, so a name that would
        // resolve to a real, Root-less folder still isn't root without it.
        $this->assertFalse(ComponentRootUtility::isDeclaredRootFromViewHelperName('widget.examples.demo'));
    }

    #[Test]
    public function detectsRootComponentsByFolderShapeWhenATemplateResolverIsGiven(): void
    {
        $collection = new RootDetectionComponentCollection();

        // Classic shape, still resolved without ever touching the filesystem.
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('widget.root', $collection));
        $this->assertFalse(ComponentRootUtility::isDeclaredRootFromViewHelperName('widget.item', $collection));

        // Folder-shape default: no Root.* sibling in the file's own directory - a nested example
        // folder, and a flat multi-file folder where no file matches the folder's own name.
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('widget.examples.demo', $collection));
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('icon.menu', $collection));
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('icon.copy', $collection));

        // Regression: a tiered Root file (3+ segments ending in ".root") used to be missed by the
        // classic rule's hardcoded segment[1] check - the folder-shape check's "this file IS the
        // Root" fast path fixes it for free.
        $this->assertTrue(ComponentRootUtility::isDeclaredRootFromViewHelperName('tier.widget.root', $collection));
    }
}
