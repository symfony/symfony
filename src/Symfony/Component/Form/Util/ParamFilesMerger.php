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
 * @author Priyadi Iman Nurcahyo <priyadi@rekalogika.com>
 */
class ParamFilesMerger
{
    private array $path;
    private array $params;
    private array $files;

    /**
     * @param array $path   The path to the current element, empty means the root
     * @param array $params The parameters
     * @param array $files  The files
     */
    public function __construct(array $path, array $params, array $files)
    {
        $this->path = $path;
        $this->params = $params;
        $this->files = $files;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        $paramsValue = $this->getParamsValue();
        $filesValue = $this->getFilesValue();

        if (null === $paramsValue) {
            return $filesValue;
        } elseif (\is_array($paramsValue)) {
            if (self::isFileUpload($filesValue)) {
                return $filesValue; // if the array is a file upload field, it has the precedence
            } elseif (\is_array($filesValue)) {
                return $this->getResultArray($paramsValue, $filesValue);
            } else { // $filesValue has a non-array value
                return $paramsValue; // params has the precedence
            }
        } else { // $paramsValue has a non-array value
            if (self::isFileUpload($filesValue)) {
                return $filesValue; // if the array is a file upload field, it has the precedence
            } else {
                return $paramsValue;
            }
        }
    }

    /**
     * @param UploadedFile|array $value
     */
    private static function isFileUpload($value): bool
    {
        if ($value instanceof UploadedFile) {
            return true;
        }

        if (!\is_array($value)) {
            return false;
        }

        $keys = array_keys($value);
        sort($keys);

        return $keys === ['error', 'name', 'size', 'tmp_name', 'type'];
    }

    private static function doesNotContainArrayOrFileUpload(array $array): bool
    {
        foreach ($array as $value) {
            if (\is_array($value) && !self::isFileUpload($value)) {
                return false;
            }
        }

        return true;
    }

    private function getResultArray(array $paramsValue, array $filesValue): array
    {
        // if both are lists and both does not contains array, then merge them and return
        if (
            array_is_list($paramsValue)
            && self::doesNotContainArrayOrFileUpload($paramsValue)
            && array_is_list($filesValue)
            && self::doesNotContainArrayOrFileUpload($filesValue)
        ) {
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
            $path = $this->path;
            $path[] = $key;

            $node = new self($path, $this->params, $this->files);

            $result[$key] = $node->getResult();
        }

        return $result;
    }

    /**
     * Gets the value of the current element in the params according to the path.
     *
     * @return mixed
     */
    private function getParamsValue()
    {
        $params = $this->params;

        foreach ($this->path as $key) {
            $params = $params[$key] ?? null;

            if (null === $params) {
                return null;
            }
        }

        return $params;
    }

    /**
     * Gets the value of the current element in the files according to the path.
     *
     * @return mixed
     */
    private function getFilesValue()
    {
        $files = $this->files;

        foreach ($this->path as $key) {
            $files = $files[$key] ?? null;

            if (null === $files) {
                return null;
            }
        }

        return $files;
    }
}
