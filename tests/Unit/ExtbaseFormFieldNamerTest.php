<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ExtbaseFormFieldNamer;
use PHPUnit\Framework\Attributes\Test;

final class ExtbaseFormFieldNamerTest extends TestCase
{
    #[Test]
    public function prefixesNestedDotNotationFieldNamesForTrustedPropertiesAndSubmission(): void
    {
        $namer = new ExtbaseFormFieldNamer();

        $this->assertSame('tx_docs_registration[eventRegistration][person][name]', $namer->prefixFieldName(
            'person.name',
            'eventRegistration',
            'tx_docs_registration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $namer->prefixFieldName(
            'persons[0].name',
            'eventRegistration',
            'tx_docs_registration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $namer->prefixFieldName(
            'persons.0.name',
            'eventRegistration',
            'tx_docs_registration',
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][tags][]', $namer->prefixFieldName(
            'tags[]',
            'eventRegistration',
            'tx_docs_registration',
        ));
    }

    #[Test]
    public function omitsTheObjectNameSegmentWhenNoneIsGiven(): void
    {
        $namer = new ExtbaseFormFieldNamer();

        $this->assertSame('tx_docs_registration[name]', $namer->prefixFieldName('name', null, 'tx_docs_registration'));
    }

    #[Test]
    public function omitsTheFieldNamePrefixSegmentWhenEmpty(): void
    {
        $namer = new ExtbaseFormFieldNamer();

        $this->assertSame('eventRegistration[name]', $namer->prefixFieldName('name', 'eventRegistration', ''));
    }

    #[Test]
    public function returnsAnEmptyStringForAnEmptyFieldName(): void
    {
        $namer = new ExtbaseFormFieldNamer();

        $this->assertSame('', $namer->prefixFieldName('', 'eventRegistration', 'tx_docs_registration'));
    }
}
