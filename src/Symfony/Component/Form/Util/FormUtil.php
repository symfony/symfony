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
     *
     * @return bool
     */
    public static function isEmpty($data)
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
        return self::mergeAtPath($params, $files);
    }

    private static function mergeAtPath(array $params, array $files, array $path = [])
    {
        $paramsValue = self::getValueAtPath($params, $path);
        $filesValue = self::getValueAtPath($files, $path);

        if (null === $paramsValue) {
            return $filesValue;
        }

        if (\is_array($paramsValue) && self::isFileUpload($filesValue)) {
            return $filesValue; // if the array is a file upload field, it has the precedence
        }

        if (\is_array($paramsValue) && \is_array($filesValue)) {
            // if both are lists and both does not contain array, then merge them and return
            if (array_is_list($paramsValue) && self::doesNotContainNonFileUploadArray($paramsValue) && array_is_list($filesValue) && self::doesNotContainNonFileUploadArray($filesValue)) {
                return array_merge($paramsValue, $filesValue);
            }

            // heuristics to preserve order, the bigger array wins
            if (\count($filesValue) > \count($paramsValue)) {
                $keys = array_unique(array_merge(array_keys($filesValue), array_keys($paramsValue)));
            } else {
                $keys = array_unique(array_merge(array_keys($paramsValue), array_keys($filesValue)));
            }

            $result = [];

            foreach ($keys as $key) {
                $subPath = $path;
                $subPath[] = $key;

                $result[$key] = self::mergeAtPath($params, $files, $subPath);
            }

            return $result;
        }

        if (\is_array($paramsValue)) {
            return $paramsValue; // params has the precedence
        }

        if (self::isFileUpload($filesValue)) {
            return $filesValue; // if the array is a file upload field, it has the precedence
        }

        return $paramsValue;
    }

    private static function getValueAtPath(array $params, array $path)
    {
        foreach ($path as $key) {
            if (null === $params = $params[$key] ?? null) {
                return null;
            }
        }

        return $params;
    }

    /**
     * @param UploadedFile|array $value
     */
    private static function isFileUpload($value): bool
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
