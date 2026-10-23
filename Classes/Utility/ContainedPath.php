<?php

declare(strict_types=1);

namespace Jramke\FluidPrimitives\Utility;

/**
 * Keeps a path inside a folder after `..` and symlinks are resolved, for the files `ui:add` reads and writes.
 */
final class ContainedPath
{
    /**
     * The content of the file `$relativePath` below `$folder`, or false when there is no such file or the path
     * leads out of the folder.
     */
    public static function read(string $folder, string $relativePath): string|false
    {
        $root = realpath($folder);
        if ($root === false) {
            return false;
        }

        $file = realpath($root . '/' . $relativePath);
        if ($file === false || !str_starts_with($file, $root . '/') || !is_file($file)) {
            return false;
        }

        return file_get_contents($file);
    }

    /**
     * Whether `$directory` is `$root` or below it once symlinks are resolved. Both have to exist.
     */
    public static function isInside(string $root, string $directory): bool
    {
        $root = realpath($root);
        $directory = realpath($directory);

        return $root !== false && $directory !== false && str_starts_with($directory . '/', $root . '/');
    }
}
