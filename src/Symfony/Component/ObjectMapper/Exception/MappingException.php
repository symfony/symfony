<?php
/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Exception;

/**
 * @experimental
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 * @author Devoton <oton.traore@gmail.com>
 */
class MappingException extends \RuntimeException
{
    public static function forProperty(string $sourcePath, string $targetPath, string $expected, string $actual, $value = null): self
    {
        $message = sprintf(
            'Cannot map `%s` => `%s`: Expected %s, got %s%s',
            $sourcePath,
            $targetPath,
            $expected,
            $actual,
            $value !== null ? sprintf(' (value: %s)', var_export($value, true)) : ''
        );

        return new self($message);
    }
}
