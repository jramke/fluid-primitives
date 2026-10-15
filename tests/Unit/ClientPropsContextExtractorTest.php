<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Attributes\ExposeToClient;
use Jramke\FluidPrimitives\Contexts\AbstractComponentContext;
use Jramke\FluidPrimitives\Contexts\AbstractGlobalContext;
use Jramke\FluidPrimitives\Tests\Fixtures\GlobalContext\ProjectGlobalContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ClientPropsContextExtractor;
use PHPUnit\Framework\Attributes\Test;

final class ClientPropsContextExtractorTest extends TestCase
{
    #[Test]
    public function buildsNestedObjectsFromADotPathNameOnTheGlobalContext(): void
    {
        $this->assertSame(
            ['siteName' => 'Demo', 'lightbox' => ['icons' => ['close' => '<svg/>']], 'plain' => true],
            ClientPropsContextExtractor::extract(new ProjectGlobalContext()),
        );
    }

    #[Test]
    public function mergesSiblingsUnderOneDotPathParent(): void
    {
        $context = new class extends AbstractGlobalContext {
            #[ExposeToClient(name: 'a.b')]
            public function getOne(): int
            {
                return 1;
            }

            #[ExposeToClient(name: 'a.c')]
            public function getTwo(): int
            {
                return 2;
            }
        };

        $this->assertSame(['a' => ['b' => 1, 'c' => 2]], ClientPropsContextExtractor::extract($context));
    }

    #[Test]
    public function keepsADotPathNameFlatOnAComponentContext(): void
    {
        $context = new class extends AbstractComponentContext {
            #[ExposeToClient(name: 'a.b')]
            public function getOne(): int
            {
                return 1;
            }

            #[ExposeToClient(excludeIfNull: true)]
            public function getNothing(): ?int
            {
                return null;
            }
        };

        $this->assertSame(['a.b' => 1], ClientPropsContextExtractor::extract($context));
    }

    #[Test]
    public function leavesOutAGlobalThatCannotBeEncodedOutsideDevelopment(): void
    {
        $context = new class extends AbstractGlobalContext {
            #[ExposeToClient]
            public function getBroken(): float
            {
                return NAN;
            }

            #[ExposeToClient]
            public function getFine(): string
            {
                return 'fine';
            }
        };

        // no Environment is initialized in a unit test, which counts as production
        $this->assertSame(['fine' => 'fine'], ClientPropsContextExtractor::extract($context));
    }

    #[Test]
    public function stillDiscoversExposedMethodsByClassName(): void
    {
        $discovered = ClientPropsContextExtractor::discover(ProjectGlobalContext::class);

        $this->assertSame(['siteName', 'lightbox.icons', 'plain', 'missing'], array_keys($discovered));
        $this->assertTrue($discovered['missing']['excludeIfNull']);
    }
}
