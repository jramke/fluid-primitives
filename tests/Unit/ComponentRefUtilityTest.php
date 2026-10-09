<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentNameUtility;
use Jramke\FluidPrimitives\Utility\ComponentRefUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ComponentRefUtilityTest extends TestCase
{
    /**
     * The vectors are shared with the client: tests/Client/hydration.test.ts feeds the same file to
     * `ComponentHydrator`, so what the server renders and what the client looks for cannot drift apart.
     *
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function partNamingVectors(): iterable
    {
        $json = file_get_contents(__DIR__ . '/../Fixtures/part-naming.json');
        $vectors = json_decode($json === false ? '[]' : $json, true, flags: JSON_THROW_ON_ERROR);

        foreach ($vectors as $vector) {
            yield $vector['componentName'] . '.' . $vector['part'] => [$vector];
        }
    }

    /**
     * @param array<string, string> $vector
     */
    #[Test]
    #[DataProvider('partNamingVectors')]
    public function namesPartsTheWayTheClientHydratorDoes(array $vector): void
    {
        $clientBaseName = ComponentNameUtility::camelCaseToLowerCaseDashed($vector['componentName']);

        $this->assertSame($vector['clientBaseName'], $clientBaseName);
        $this->assertSame($vector['attribute'], ComponentRefUtility::getAttributeName(
            $clientBaseName,
            $vector['part'],
        ));
        $this->assertSame($vector['scopeKey'], ComponentRefUtility::getScopeKey(
            $clientBaseName,
            $vector['rootId'],
            $vector['part'],
            $vector['value'] ?? null,
        ));
    }
}
