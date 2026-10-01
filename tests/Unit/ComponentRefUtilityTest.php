<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Tests\TestCase;
use Jramke\FluidPrimitives\Utility\ComponentRefUtility;
use PHPUnit\Framework\Attributes\Test;

final class ComponentRefUtilityTest extends TestCase
{
    #[Test]
    public function derivesTheAttributeNameFromComponentAndPartMirroringZagsPartAttributes(): void
    {
        $this->assertSame('data-dialog-close-trigger', ComponentRefUtility::getAttributeName('dialog', 'closeTrigger'));
        $this->assertSame('data-file-upload-item-delete-trigger', ComponentRefUtility::getAttributeName(
            'file-upload',
            'itemDeleteTrigger',
        ));
        // A dot of a tiered/example component identity isn't valid in a CSS attribute selector.
        $this->assertSame('data-molecules-checkbox-group-root', ComponentRefUtility::getAttributeName(
            'molecules.checkbox-group',
            'root',
        ));
    }

    #[Test]
    public function buildsScopeKeysFromComponentRootPartAndOptionalValue(): void
    {
        $this->assertSame('combobox:«f1»:item-template', ComponentRefUtility::getScopeKey(
            'combobox',
            '«f1»',
            'itemTemplate',
        ));
        $this->assertSame('field-array:«f1»:item:2', ComponentRefUtility::getScopeKey(
            'field-array',
            '«f1»',
            'item',
            '2',
        ));
    }
}
