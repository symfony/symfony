<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AssetMapper\Compiler;

use Symfony\Component\AssetMapper\Exception\RuntimeException;

/**
 * Helps resolve "../" and "./" in paths.
 *
 * @experimental
 *
 * @internal
 */
trait AssetCompilerPathResolverTrait
{
    /**
     * Given the current directory and a relative filename, returns the
     * resolved path.
     *
     * For example:
     *
     *    // returns "subdir/another-dir/other.js"
     *    $this->resolvePath('subdir/another-dir/third-dir', '../other.js');
     */
    private function resolvePath(string $directory, string $filename): string
    {
        $pathParts = array_filter(explode('/', $directory.'/'.$filename));
        $output = [];

        foreach ($pathParts as $part) {
            if ('..' === $part) {
                if (0 === \count($output)) {
                    throw new RuntimeException(sprintf('Cannot import the file "%s": it is outside the current "%s" directory.', $filename, $directory));
                }

                array_pop($output);
                continue;
            }

            if ('.' === $part) {
                // skip
                continue;
            }

            $output[] = $part;
        }

        return implode('/', $output);
    }

    private function createRelativePath(string $fromPath, string $toPath): string
    {
        $fromPath = rtrim($fromPath, '/');
        $toPath = rtrim($toPath, '/');

        $fromParts = explode('/', $fromPath);
        $toParts = explode('/', $toPath);

        // Remove the file names from both paths
        array_pop($fromParts);
        array_pop($toParts);

        // Find the common part of the paths
        while (\count($fromParts) > 0 && \count($toParts) > 0 && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        // Add "../" for each remaining directory in the from path
        $relativePath = str_repeat('../', \count($fromParts));

        // Add the remaining directories in the to path
        $relativePath .= implode('/', $toParts);
        $relativePath = rtrim($relativePath, '/');

        // Add the file name to the relative path
        $relativePath .= '/'.basename($toPath);

        return ltrim($relativePath, '/');
    }
}
