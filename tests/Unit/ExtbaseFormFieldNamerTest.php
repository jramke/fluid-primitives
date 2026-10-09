<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ExtbaseFormFieldNamer;
use PHPUnit\Framework\Attributes\Test;

final class ExtbaseFormFieldNamerTest extends TestCase
{
    #[Test]
    public function prefixesFieldNamesTheWayExtbaseReadsThemBack(): void
    {
        $namer = new ExtbaseFormFieldNamer();
        $prefix = 'tx_docs_registration';

        // dot and bracket notation are one path: what trusted properties and the browser's submission agree on
        $this->assertSame('tx_docs_registration[eventRegistration][person][name]', $namer->prefixFieldName(
            'person.name',
            'eventRegistration',
            $prefix,
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $namer->prefixFieldName(
            'persons[0].name',
            'eventRegistration',
            $prefix,
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][persons][0][name]', $namer->prefixFieldName(
            'persons.0.name',
            'eventRegistration',
            $prefix,
        ));
        $this->assertSame('tx_docs_registration[eventRegistration][tags][]', $namer->prefixFieldName(
            'tags[]',
            'eventRegistration',
            $prefix,
        ));

        // a missing object name or plugin prefix just leaves its segment out
        $this->assertSame('tx_docs_registration[name]', $namer->prefixFieldName('name', null, $prefix));
        $this->assertSame('eventRegistration[name]', $namer->prefixFieldName('name', 'eventRegistration', ''));
    }
}
