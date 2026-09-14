<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Registry\HydrationScriptBuilder;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class HydrationScriptBuilderTest extends TestCase
{
    #[Test]
    public function pettyPrintsAndKeepsWhitespaceInDevelopment(): void
    {
        $js = (new HydrationScriptBuilder())->build(
            ['accordion' => ['«f1»' => ['multiple' => true]]],
            ['locale' => 'en_US'],
            development: true,
        );

        $this->assertStringContainsString("\n", $js);
        $this->assertStringContainsString('window.FluidPrimitives', $js);
        $this->assertStringContainsString('"locale": "en_US"', $js);
    }

    #[Test]
    public function minifiesToASingleLineOutsideOfDevelopment(): void
    {
        $js = (new HydrationScriptBuilder())->build(
            ['accordion' => ['«f1»' => ['multiple' => true]]],
            ['locale' => 'en_US'],
            development: false,
        );

        $this->assertStringNotContainsString("\n", $js);
        $this->assertStringContainsString('window.FluidPrimitives', $js);
        $this->assertStringContainsString('"locale":"en_US"', $js);
    }

    #[Test]
    public function escapesScriptTagBreakoutInDevelopment(): void
    {
        $js = (new HydrationScriptBuilder())->build(
            ['accordion' => ['«f1»' => ['label' => '</script><script>alert(1)</script>']]],
            ['locale' => 'en_US'],
            development: true,
        );

        $this->assertStringNotContainsString('</script>', $js);
    }

    #[Test]
    public function escapesScriptTagBreakoutOutsideOfDevelopment(): void
    {
        $js = (new HydrationScriptBuilder())->build(
            ['accordion' => ['«f1»' => ['label' => '</script><script>alert(1)</script>']]],
            ['locale' => 'en_US'],
            development: false,
        );

        $this->assertStringNotContainsString('</script>', $js);
    }
}
