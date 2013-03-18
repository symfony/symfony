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

        if (preg_match('#^(\d+)([bkmgt])#i', $iniMax, $match)) {
            $shift = array('b' => 0, 'k' => 10, 'm' => 20, 'g' => 30, 't' => 40);
            $iniMax = ($match[1] * (1 << $shift[strtolower($match[2])]));
        }

        return (int) $iniMax;
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
