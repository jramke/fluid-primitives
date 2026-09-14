<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Parser\Exception;

final class PropViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function throwsAtParseTimeWhenAPropIsDeclaredWithAReservedName(): void
    {
        // This is the one place a reserved name can be freshly authored (as opposed to inherited via
        // ui:useProps, which must stay allowed - see UsePropsViewHelperTest), so it's the one place
        // that needs to reject it.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The name "asChild" is reserved and cannot be used as a prop name.');

        $this->renderTemplate('<ui:prop name="asChild" type="boolean" optional="{true}" />');
    }
}
