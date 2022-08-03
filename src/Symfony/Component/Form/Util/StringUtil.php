<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Util;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class StringUtil
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Returns the trimmed data.
     */
    public static function trim(string $string): string
    {
        if (null !== $result = @preg_replace('/^[\pZ\p{Cc}\p{Cf}]+|[\pZ\p{Cc}\p{Cf}]+$/u', '', $string)) {
            return $result;
        }

        return trim($string);
    }

    /**
     * Converts a fully-qualified class name to a block prefix.
     *
     * @param string $fqcn The fully-qualified class name
     */
    public static function fqcnToBlockPrefix(string $fqcn): ?string
    {
        // Non-greedy ("+?") to match "type" suffix, if present
        if (preg_match('~([^\\\\]+?)(type)?$~i', $fqcn, $matches)) {
            return strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\\1_\\2', '\\1_\\2'], $matches[1]));
        }

        return null;
    }
}
