<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Command\ComponentListCommand;
use Jramke\FluidPrimitives\Service\RegistryService;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

final class ComponentListCommandTest extends TestCase
{
    #[Test]
    public function listsTheKeyNameAndDescriptionOfEveryComponentOfTheRegistry(): void
    {
        $registryService = $this->createStub(RegistryService::class);
        $registryService
            ->method('fetchComponentList')
            ->willReturn([
                null,
                [
                    ['key' => 'accordion', 'name' => 'Accordion', 'description' => 'A stack of sections.'],
                    ['key' => 'scroll-area', 'name' => 'Scroll Area', 'description' => 'A styled scroll container.'],
                ],
            ]);

        $tester = new CommandTester(new ComponentListCommand($registryService));
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertMatchesRegularExpression('/accordion\s+Accordion\s+A stack of sections\./', $tester->getDisplay());
        $this->assertMatchesRegularExpression(
            '/scroll-area\s+Scroll Area\s+A styled scroll container\./',
            $tester->getDisplay(),
        );
    }

    #[Test]
    public function failsWithTheMessageOfTheRegistryError(): void
    {
        $registryService = $this->createStub(RegistryService::class);
        $registryService
            ->method('fetchComponentList')
            ->willReturn([['message' => 'Failed to fetch component registry.', 'details' => null], []]);

        $tester = new CommandTester(new ComponentListCommand($registryService));
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Failed to fetch component registry.', $tester->getDisplay());
        $this->assertStringNotContainsString('Available Components', $tester->getDisplay());
    }
}
