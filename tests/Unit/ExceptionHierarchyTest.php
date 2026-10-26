<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Tests\Unit;

use Jramke\FluidPrimitives\Exception\ExceptionInterface;
use Jramke\FluidPrimitives\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

final class ExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function everyExceptionOfTheLibraryImplementsTheMarkerInterface(): void
    {
        $directory = dirname(__DIR__, 2) . '/Classes/Exception/';
        $files = glob($directory . '*Exception.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $class = 'Jramke\\FluidPrimitives\\Exception\\' . basename($file, '.php');
            if ($class === ExceptionInterface::class) {
                continue;
            }

            $this->assertTrue(is_subclass_of($class, ExceptionInterface::class), $class . ' misses ExceptionInterface');
            $this->assertTrue(is_subclass_of($class, \Throwable::class), $class . ' is not throwable');
        }
    }

    #[Test]
    public function noClassThrowsABareRuntimeOrInvalidArgumentException(): void
    {
        $offenders = [];
        $classes = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/Classes', \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($classes as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string)file_get_contents($file->getPathname());
            if (
                preg_match(
                    '/new \\\\(?:Runtime|InvalidArgument)Exception\b|^use \\\\?(?:Runtime|InvalidArgument)Exception;/m',
                    $source,
                ) === 1
            ) {
                $offenders[] = substr((string)$file->getPathname(), strlen(dirname(__DIR__, 2)) + 1);
            }
        }

        $this->assertSame([], $offenders, 'Throw an exception of Jramke\FluidPrimitives\Exception instead.');
    }
}
