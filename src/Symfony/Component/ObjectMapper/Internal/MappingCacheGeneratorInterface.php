<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Internal;

use Symfony\Component\ObjectMapper\Exception\MappingException;

/**
 * @internal
 */
interface MappingCacheGeneratorInterface
{
    /**
     * Generates the PHP code for a mapping function between a source and a target class.
     *
     * @param class-string $sourceClass
     * @param class-string $targetClass
     *
     * @throws MappingException if code generation fails
     */
    public function generate(string $sourceClass, string $targetClass): string;

    /**
     * Writes the generated mapping code to a cache file.
     */
    public function write(string $cacheFile, string $code): void;
}
