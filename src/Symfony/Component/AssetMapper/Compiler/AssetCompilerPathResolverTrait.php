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

use Symfony\Component\Asset\Exception\RuntimeException;

/**
 * Helps resolve "../" and "./" in paths.
 *
 * @experimental
 *
 * @internal
 */
trait AssetCompilerPathResolverTrait
{
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
}
