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

use Symfony\Component\HttpFoundation\File\UploadedFile;

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
     * Merges query string or post parameters with uploaded files.
     */
    public static function mergeParamsAndFiles(array $params, array $files): array
    {
        return self::merge($params, $files);
    }

    private static function merge(mixed $params, mixed $files): mixed
    {
        if (null === $params) {
            return $files;
        }

        if (\is_array($params) && self::isFileUpload($files)) {
            return $files; // if the array is a file upload field, it has the precedence
        }

        if (\is_array($params) && \is_array($files)) {
            // if both are lists and both do not contain arrays, then merge them and return
            if (array_is_list($params) && self::doesNotContainNonFileUploadArray($params) && array_is_list($files) && self::doesNotContainNonFileUploadArray($files)) {
                return array_merge($params, $files);
            }

            // heuristics to preserve order, the bigger array wins
            if (\count($files) > \count($params)) {
                $keys = array_unique(array_merge(array_keys($files), array_keys($params)));
            } else {
                $keys = array_unique(array_merge(array_keys($params), array_keys($files)));
            }

            $result = [];

            foreach ($keys as $key) {
                $result[$key] = self::merge($params[$key] ?? null, $files[$key] ?? null);
            }

            return $result;
        }

        if (\is_array($params)) {
            return $params; // params has the precedence
        }

        if (self::isFileUpload($files)) {
            return $files; // if the array is a file upload field, it has the precedence
        }

        return $params;
    }

    private static function isFileUpload(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }

        if (!\is_array($value) || !\in_array(\count($value), [5, 6], true)) {
            return false;
        }

        if (\array_key_exists('full_path', $value)) {
            unset($value['full_path']);
        }

        $keys = array_keys($value);
        sort($keys);

        return ['error', 'name', 'size', 'tmp_name', 'type'] === $keys;
    }

    private static function doesNotContainNonFileUploadArray(array $array): bool
    {
        foreach ($array as $value) {
            if (\is_array($value) && !self::isFileUpload($value)) {
                return false;
            }
        }

        return true;
    }
}
