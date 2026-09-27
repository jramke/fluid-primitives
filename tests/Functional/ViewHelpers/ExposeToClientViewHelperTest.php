<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\Fixtures\RootDetectionComponentCollection;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ExposeToClientViewHelperTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['fixture'] = [
            RootDetectionComponentCollection::class,
        ];
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fluid']['namespaces']['fixture']);
        parent::tearDown();
    }

    #[Test]
    public function allowsUiExposeToClientInsideAComponentThatsRootOnlyViaTheFolderShapeDefault(): void
    {
        // Regression test: "widget.examples.demo" (RootDetection/Widget/Examples/Demo.fluid.html) is
        // root only because its own folder has no Root.fluid.html sibling - before
        // ComponentIdentity::$isDeclaredRoot existed, `<ui:exposeToClient />` inside such a
        // template threw "can only be used in a root component" even though the compile-time check
        // (AbstractComponentCollection::getComponentDefinition()) already knew it was root.
        $view = $this->getView();
        $view
            ->getRenderingContext()
            ->getViewHelperResolver()
            ->addNamespace('fixture', new RootDetectionComponentCollection());

        $html = $this->renderTemplate('<fixture:widget.examples.demo>Content</fixture:widget.examples.demo>');

        $this->assertStringContainsString('Content', $html);
    }
}
