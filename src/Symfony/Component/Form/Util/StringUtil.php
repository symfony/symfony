<?php

namespace Symfony\Component\Form\Util;

/**
 * @author Issei Murasawa <issei.m7@gmail.com>
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
     *
     * @param mixed $data
     *
     * @return mixed
     */
    public static function trim($data)
    {
        if (is_string($data)) {
            if (null !== $result = @preg_replace('/^[\pZ\p{Cc}]+|[\pZ\p{Cc}]+$/u', '', $data)) {
                $data = $result;
            } else {
                $data = trim($data);
            }
        }

        return $data;
    }
}
