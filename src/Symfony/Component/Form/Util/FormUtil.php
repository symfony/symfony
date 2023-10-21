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
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormUtil
{
    /**
     * This class should not be instantiated.
     */
    private function __construct()
    {
    }

    /**
     * Returns whether the given data is empty.
     *
     * This logic is reused multiple times throughout the processing of
     * a form and needs to be consistent. PHP keyword `empty` cannot
     * be used as it also considers 0 and "0" to be empty.
     */
    public static function isEmpty(mixed $data): bool
    {
        // Should not do a check for [] === $data!!!
        // This method is used in occurrences where arrays are
        // not considered to be empty, ever.
        return null === $data || '' === $data;
    }

    /**
     * Recursively replaces or appends elements of the first array with elements
     * of second array. If the key is an integer, the values will be appended to
     * the new array; otherwise, the value from the second array will replace
     * the one from the first array.
     */
    public static function mergeParamsAndFiles(array $params, array $files): array
    {
        $result = [];

        foreach ($params as $key => $value) {
            if (\is_array($value) && \is_array($files[$key] ?? null)) {
                $value = self::mergeParamsAndFiles($value, $files[$key]);
                unset($files[$key]);
            }
            if (\is_int($key)) {
                $result[] = $value;
            } else {
                $result[$key] = $value;
            }
        }

        return array_merge($result, $files);
    }
}
