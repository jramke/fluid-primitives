<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\TypeConverter;

use Jramke\FluidPrimitives\Utility\Typed;
use TYPO3\CMS\Extbase\Error\Error;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;
use TYPO3\CMS\Extbase\Property\TypeConverter\DateTimeConverter;

/**
 * Maps the ISO dates (`2025-06-15`) the date picker submits to a `DateTime` or `DateTimeImmutable`
 * at midnight, so a property needs no `dateFormat` configuration.
 *
 * Extbase only maps its W3C date-time (`2025-06-15T00:00:00+02:00`), and only to `DateTime`. Every
 * other string goes to its own converter, which still honours a configured `dateFormat`.
 */
final class IsoDateConverter extends AbstractTypeConverter
{
    public function __construct(
        private readonly DateTimeConverter $dateTimeConverter,
    ) {}

    // Registered for string sources only, see Services.yaml.
    public function convertFrom(
        mixed $source,
        string $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null,
    ): \DateTimeInterface|Error|null {
        $string = Typed::string($source);
        /** @var class-string<\DateTime|\DateTimeImmutable> $dateClass */
        $dateClass = $targetType;
        // @mago-expect analysis:possibly-invalid-argument
        $hasOwnFormat =
            $configuration?->getConfigurationValue(
                DateTimeConverter::class,
                DateTimeConverter::CONFIGURATION_DATE_FORMAT,
            ) !== null;

        if (!$hasOwnFormat && preg_match('/^\d{4}-\d{2}-\d{2}$/', $string) === 1) {
            $date = $dateClass::createFromFormat('!Y-m-d', $string);

            // An impossible date like 2025-02-30 rolls over to the next month instead of failing.
            if ($date !== false && $date->format('Y-m-d') === $string) {
                return $date;
            }
        }

        if (!is_a($dateClass, \DateTimeImmutable::class, allow_string: true)) {
            return $this->dateTimeConverter->convertFrom(
                $string,
                $dateClass,
                $convertedChildProperties,
                $configuration,
            );
        }

        $date = $this->dateTimeConverter->convertFrom(
            $string,
            \DateTime::class,
            $convertedChildProperties,
            $configuration,
        );

        return $date instanceof \DateTime ? $dateClass::createFromMutable($date) : $date;
    }
}
