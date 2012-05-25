<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Util;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerParams
{
    /**
     * Returns maximum post size in bytes.
     *
     * @return null|integer The maximum post size in bytes
     */
    public function getPostMaxSize()
    {
        $iniMax = $this->getNormalizedIniPostMaxSize();

        if ('' === $iniMax) {
            return null;
        }

        $max = (int) $iniMax;

        switch (substr($iniMax, -1)) {
            case 'G':
                $max *= 1024;
            case 'M':
                $max *= 1024;
            case 'K':
                $max *= 1024;
        }

        return $max;
    }

    /**
     * Returns the normalized "post_max_size" ini setting.
     *
     * @return string
     */
    public function getNormalizedIniPostMaxSize()
    {
        return strtoupper(trim(ini_get('post_max_size')));
    }

    /**
     * Returns the content length of the request.
     *
     * @return mixed The request content length.
     */
    public function getContentLength()
    {
        return isset($_SERVER['CONTENT_LENGTH'])
            ? (int) $_SERVER['CONTENT_LENGTH']
            : null;
    }

}
