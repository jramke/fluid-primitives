<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Registry\HydrationScriptBuilder;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class HydrationScriptBuilderTest extends TestCase
{
    #[Test]
    public function prettyPrintsInDevelopmentAndMinifiesToASingleLineOtherwise(): void
    {
        $builder = new HydrationScriptBuilder();
        $registry = ['accordion' => ['«f1»' => ['multiple' => true]]];

        $development = $builder->build($registry, ['locale' => 'en_US'], [], development: true);
        $this->assertStringContainsString("\n", $development);
        $this->assertStringContainsString('window.FluidPrimitives', $development);
        $this->assertStringContainsString('"locale": "en_US"', $development);

        $production = $builder->build($registry, ['locale' => 'en_US'], [], development: false);
        $this->assertStringNotContainsString("\n", $production);
        $this->assertStringContainsString('window.FluidPrimitives', $production);
        $this->assertStringContainsString('"locale":"en_US"', $production);
    }

    #[Test]
    public function keepsTheTextOfTheDataAsItIsWhenMinified(): void
    {
        $label = "a  =  ( b ) ;  c\t{ d }";

        $js = (new HydrationScriptBuilder())->build(
            ['select' => ['«f1»' => ['label' => $label]]],
            ['locale' => 'en_US'],
            [],
            development: false,
        );

        $this->assertStringContainsString('"label":' . json_encode($label), $js);
    }

    #[Test]
    public function escapesScriptTagBreakoutInBothModes(): void
    {
        $builder = new HydrationScriptBuilder();
        $registry = ['accordion' => ['«f1»' => ['label' => '</script><script>alert(1)</script>']]];

        foreach ([true, false] as $development) {
            $js = $builder->build($registry, ['locale' => 'en_US'], [], development: $development);

            $this->assertStringNotContainsString('</script>', $js, $development ? 'development' : 'production');
        }
    }

    #[Test]
    public function includesNestedComponentsByScope(): void
    {
        $js = (new HydrationScriptBuilder())->build(
            ['field' => ['«f1»' => ['name' => 'firstName']]],
            ['locale' => 'en_US'],
            ['field-array:«f0»:itemTemplate' => [['name' => 'field', 'id' => '«f1»']]],
            development: true,
        );

        $this->assertStringContainsString('nestedComponents', $js);
        $this->assertStringContainsString('field-array:«f0»:itemTemplate', $js);
    }
}
