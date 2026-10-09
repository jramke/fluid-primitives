<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\TypeConverter;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

final class IsoDateConverterTest extends FunctionalTestCase
{
    #[Test]
    public function mapsIsoDatesToMidnightWithoutConfiguration(): void
    {
        $mapper = $this->get(PropertyMapper::class);

        foreach ([\DateTime::class, \DateTimeImmutable::class] as $targetType) {
            $date = $mapper->convert('2025-06-15', $targetType);

            $this->assertInstanceOf($targetType, $date);
            $this->assertSame('2025-06-15 00:00:00', $date->format('Y-m-d H:i:s'), $targetType);
        }

        // An impossible date must not roll over to the next month.
        $this->assertNull($mapper->convert('2025-02-30', \DateTime::class));
    }

    #[Test]
    public function leavesEveryOtherStringToExtbasesOwnConversion(): void
    {
        $mapper = $this->get(PropertyMapper::class);

        $w3c = $mapper->convert('2025-06-15T10:30:00+02:00', \DateTime::class);
        $this->assertInstanceOf(\DateTime::class, $w3c);
        $this->assertSame('2025-06-15T10:30:00+02:00', $w3c->format('c'));

        // Extbase itself cannot map to a DateTimeImmutable.
        $immutable = $mapper->convert('2025-06-15T10:30:00+02:00', \DateTimeImmutable::class);
        $this->assertInstanceOf(\DateTimeImmutable::class, $immutable);
        $this->assertSame('2025-06-15T10:30:00+02:00', $immutable->format('c'));

        $this->assertNull($mapper->convert('', \DateTimeImmutable::class));

        $configuration = new PropertyMappingConfiguration();
        $configuration->setTypeConverterOption(
            DateTimeConverter::class,
            DateTimeConverter::CONFIGURATION_DATE_FORMAT,
            'd.m.Y',
        );
        $german = $mapper->convert('15.06.2025', \DateTime::class, $configuration);
        $this->assertInstanceOf(\DateTime::class, $german);
        $this->assertSame('2025-06-15', $german->format('Y-m-d'));
    }
}
