<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Helper;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

final class TestEntity extends AbstractEntity
{
    private ?TestEntity $nested = null;

    public function setTestUid(?int $uid): void
    {
        $this->uid = $uid;
    }

    public function setNested(?TestEntity $nested): void
    {
        $this->nested = $nested;
    }

    public function getNested(): ?TestEntity
    {
        return $this->nested;
    }
}
