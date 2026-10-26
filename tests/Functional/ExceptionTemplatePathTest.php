<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Functional;

use Jramke\FluidPrimitives\Exception\ExceptionInterface;
use Jramke\FluidPrimitives\Exception\HydrationException;
use Jramke\FluidPrimitives\Exception\InvalidPropValueException;
use Jramke\FluidPrimitives\Exception\InvalidSlotContentException;
use Jramke\FluidPrimitives\Exception\MissingContextException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception as ViewHelperException;

/**
 * Fluid names the template of a failed ViewHelper only for an exception that extends its own, and only a template
 * file has a path - so each case renders a file and expects the path in the message and the library's exception among
 * the previous ones.
 */
final class ExceptionTemplatePathTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function failingTemplates(): iterable
    {
        yield 'a part outside its root (ViewHelper)' => [
            '<primitives:combobox.item item="{value: \'a\', label: \'A\'}">A</primitives:combobox.item>',
            MissingContextException::class,
        ];
        yield 'a context outside its component (pipeline)' => [
            '<ui:ref name="root" context="select" />',
            MissingContextException::class,
        ];
        yield 'slot content of the wrong shape (pipeline)' => [
            '<primitives:dialog.root><primitives:dialog.trigger asChild="{true}"><a>One</a><b>Two</b></primitives:dialog.trigger></primitives:dialog.root>',
            InvalidSlotContentException::class,
        ];
        yield 'an unknown option (DTO translated by its ViewHelper)' => [
            '<ui:listCollection items="{0: {value: \'a\', label: \'A\'}}" groupSort="sideways" />',
            InvalidPropValueException::class,
        ];
        yield 'a duplicate rootId (hydration)' => [
            '<primitives:dialog.root rootId="same"><primitives:dialog.trigger>A</primitives:dialog.trigger></primitives:dialog.root><primitives:dialog.root rootId="same"><primitives:dialog.trigger>B</primitives:dialog.trigger></primitives:dialog.root>',
            HydrationException::class,
        ];
    }

    /**
     * @param class-string $expected
     */
    #[Test]
    #[DataProvider('failingTemplates')]
    public function namesTheTemplateAndKeepsTheLibrarysException(string $template, string $expected): void
    {
        $file = (string)tempnam(sys_get_temp_dir(), 'exception-path-');
        file_put_contents($file, $template);

        try {
            $view = $this->getView();
            $view->getRenderingContext()->getTemplatePaths()->setTemplatePathAndFilename($file);
            $view->render();
            $this->fail('The template was not rejected.');
        } catch (ViewHelperException $exception) {
            $this->assertStringContainsString($file, $exception->getMessage());
            $thrown = $exception;
            while ($thrown instanceof \Throwable && !$thrown instanceof $expected) {
                $thrown = $thrown->getPrevious();
            }
            $this->assertInstanceOf($expected, $thrown);
            $this->assertInstanceOf(ExceptionInterface::class, $thrown);
        } finally {
            unlink($file);
        }
    }
}
