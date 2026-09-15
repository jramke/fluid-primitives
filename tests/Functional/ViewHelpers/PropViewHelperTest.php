<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional\ViewHelpers;

use Jramke\FluidPrimitives\Constants;
use Jramke\FluidPrimitives\Tests\Functional\FunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\Parser\Exception;

final class PropViewHelperTest extends FunctionalTestCase
{
    #[Test]
    public function rejectsEveryReservedPropName(): void
    {
        // This is the one place a reserved name can be freshly authored (as opposed to inherited via
        // ui:useProps, which must stay allowed - see UsePropsViewHelperTest), so it's the one place
        // that needs to reject it - for every name in Constants::RESERVED_PROPS, not just one, so
        // adding a new reserved name without wiring it up here would go unnoticed.
        foreach (Constants::RESERVED_PROPS as $reservedName) {
            try {
                $this->renderTemplate(sprintf('<ui:prop name="%s" type="boolean" optional="{true}" />', $reservedName));
                $this->fail(sprintf('Expected declaring a prop named "%s" to throw.', $reservedName));
            } catch (Exception $exception) {
                $this->assertStringContainsString(
                    sprintf('The name "%s" is reserved and cannot be used as a prop name.', $reservedName),
                    $exception->getMessage(),
                );
            }
        }
    }
}
