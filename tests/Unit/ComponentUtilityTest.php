<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Contexts\AccordionContext;
use Jramke\FluidPrimitives\Contexts\BaseContext;
use Jramke\FluidPrimitives\Contexts\CollapsibleContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentUtilityTest extends TestCase
{
    private const string FIXTURE_CONTEXTS_NAMESPACE = 'Jramke\\FluidPrimitives\\Tests\\Fixtures\\Contexts';

    #[Test]
    public function resolvesClassicShapeContextClassesUnchanged(): void
    {
        // Single-file component: own name doubles as both path segments.
        $collapsible = ComponentUtility::getContextClassNameFromViewHelperName('Collapsible/Collapsible', []);
        $this->assertSame(CollapsibleContext::class, $collapsible);

        // Root.html + parts: the folder's own name owns the context, "Root" itself is discarded.
        $accordion = ComponentUtility::getContextClassNameFromViewHelperName('Accordion/Root', []);
        $this->assertSame(AccordionContext::class, $accordion);
    }

    #[Test]
    public function mirrorsFolderStructureAsNamespaceForTieredAndNestedRootComponents(): void
    {
        $namespaces = [self::FIXTURE_CONTEXTS_NAMESPACE];

        // A tiered classic root (e.g. atomic-design "molecules.checkboxGroup.root") still uses the
        // folder's own name as the class name, but namespaced under its tier segment rather than
        // flat.
        $tieredClassicRoot = ComponentUtility::getContextClassNameFromViewHelperName(
            'Molecules/CheckboxGroup/Root',
            $namespaces,
        );
        $this->assertSame(self::FIXTURE_CONTEXTS_NAMESPACE . '\\Molecules\\CheckboxGroupContext', $tieredClassicRoot);

        // A component that's root only via the folder-shape default (e.g. a nested example/demo
        // file) uses its own file name as the class name, namespaced under its full containing path.
        $nestedFolderShapeRoot = ComponentUtility::getContextClassNameFromViewHelperName(
            'CheckboxGroup/Examples/SelectAll',
            $namespaces,
        );
        $this->assertSame(
            self::FIXTURE_CONTEXTS_NAMESPACE . '\\CheckboxGroup\\Examples\\SelectAllContext',
            $nestedFolderShapeRoot,
        );
    }

    #[Test]
    public function neverFallsThroughToAnUnrelatedClassSharingTheSameLastSegment(): void
    {
        // Regression test: a naive "just use the last segment as the class name in the flat
        // namespace" scheme would make "Icon/Menu" wrongly resolve to the real, unrelated
        // `MenuContext` (a fixture stand-in for it exists flat in this namespace, proving it's
        // reachable) instead of falling back to BaseContext, because the two components merely
        // happen to share a last-segment name.
        $resolved = ComponentUtility::getContextClassNameFromViewHelperName('Icon/Menu', [
            self::FIXTURE_CONTEXTS_NAMESPACE,
        ]);
        $this->assertSame(BaseContext::class, $resolved);
    }

    #[Test]
    public function generatesUniqueIdsWithPrefix(): void
    {
        $id1 = ComponentUtility::id();
        $id2 = ComponentUtility::id();
        $id3 = ComponentUtility::id('custom');

        $this->assertStringStartsWith('«f', $id1);
        $this->assertStringEndsWith('»', $id1);
        $this->assertStringStartsWith('«custom', $id3);
        $this->assertNotSame($id1, $id2);
    }

    #[Test]
    public function generatesDifferentIdsAcrossSeparatePhpProcesses(): void
    {
        // Regression test: ComponentUtility::id() used to be a plain
        // per-process counter starting at 0 on every call. That is fine for
        // a single, full page render, but isolated, out-of-band component
        // renders from separate requests (e.g. a lazily-fetched recurring-field
        // row) each run in their own PHP process/request lifecycle. Without a
        // per-process random salt, two separate isolated renders producing the
        // exact same component structure would always generate identical ids,
        // silently colliding in the client-side hydration data once merged
        // onto the same page.
        // The autoloader lives in packages/fluid-primitives/vendor when this
        // package runs standalone (e.g. after being released), or in the
        // monorepo root's vendor when run as part of this repo.
        $candidatePaths = [
            dirname(__DIR__, 2) . '/vendor/autoload.php',
            dirname(__DIR__, 4) . '/vendor/autoload.php',
        ];
        $autoloadPath = null;
        foreach ($candidatePaths as $candidatePath) {
            if (!file_exists($candidatePath)) {
                continue;
            }

            $autoloadPath = $candidatePath;
            break;
        }
        $this->assertNotNull(
            $autoloadPath,
            'Expected a composer autoloader to exist for this standalone-process check.',
        );

        $script = sprintf('require %s; echo \Jramke\FluidPrimitives\Utility\ComponentUtility::id();', var_export(
            $autoloadPath,
            true,
        ));

        $id1 = shell_exec(sprintf('php -r %s', escapeshellarg($script)));
        $id2 = shell_exec(sprintf('php -r %s', escapeshellarg($script)));

        $this->assertNotSame($id1, $id2, 'Expected ids generated by separate PHP processes to differ.');
    }
}
