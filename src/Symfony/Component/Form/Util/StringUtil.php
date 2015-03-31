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
