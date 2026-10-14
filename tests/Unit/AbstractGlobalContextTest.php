<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Contexts\AbstractGlobalContext;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

final class AbstractGlobalContextTest extends TestCase
{
    private function createContext(): AbstractGlobalContext
    {
        return new class extends AbstractGlobalContext {
            public int $calls = 0;

            public function getPlain(): string
            {
                $this->calls++;

                return 'plain';
            }

            public function getDesignTokens(): array
            {
                return ['colors' => ['primary' => '#f00'], 'list' => ['a', 'b']];
            }

            public function getNothing(): ?string
            {
                return null;
            }
        };
    }

    #[Test]
    public function readsAKeyThroughItsGetter(): void
    {
        $context = $this->createContext();

        $this->assertSame('plain', $context->get('plain'));
        $this->assertTrue($context->has('plain'));
    }

    #[Test]
    public function memoisesTheResultOfAGetter(): void
    {
        $context = $this->createContext();

        $context->get('plain');
        $context->get('plain');
        $context['plain'];

        $this->assertSame(1, $context->calls);
    }

    #[Test]
    public function walksADotPathThroughTheArrayAGetterReturns(): void
    {
        $context = $this->createContext();

        $this->assertSame('#f00', $context->get('designTokens.colors.primary'));
        $this->assertSame(['primary' => '#f00'], $context->get('designTokens.colors'));
        $this->assertSame('b', $context->get('designTokens.list.1'));
        $this->assertSame('#f00', $context['designTokens']['colors']['primary']);
    }

    #[Test]
    public function answersAMissingKeyWithNullWithoutThrowing(): void
    {
        $context = $this->createContext();

        $this->assertNull($context->get('missing'));
        $this->assertNull($context->get('designTokens.missing.deeper'));
        $this->assertNull($context->get('plain.deeper'));
        $this->assertNull($context->get(''));
        $this->assertFalse($context->has('missing'));
        $this->assertFalse($context->has('nothing'));
        $this->assertFalse(isset($context['missing']));
    }

    #[Test]
    public function keepsTheRequestOfTheFirstInitialization(): void
    {
        $context = $this->createContext();
        $this->assertNull($context->getRequest());

        $first = new ServerRequest();
        $context->initialize($first);
        $context->initialize(new ServerRequest());

        $this->assertSame($first, $context->getRequest());
    }

    #[Test]
    public function isReadOnly(): void
    {
        $context = $this->createContext();

        $this->expectException(\LogicException::class);
        $context['plain'] = 'changed';
    }
}
