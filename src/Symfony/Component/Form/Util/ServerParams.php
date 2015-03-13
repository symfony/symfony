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

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ServerParams
{
    private $requestStack;

    public function __construct(RequestStack $requestStack = null)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Returns maximum post size in bytes.
     *
     * @return null|int The maximum post size in bytes
     */
    public function getPostMaxSize()
    {
        $iniMax = strtolower($this->getNormalizedIniPostMaxSize());

        if ('' === $iniMax) {
            return;
        }

        $max = ltrim($iniMax, '+');
        if (0 === strpos($max, '0x')) {
            $max = intval($max, 16);
        } elseif (0 === strpos($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($iniMax, -1)) {
            case 't': $max *= 1024;
            case 'g': $max *= 1024;
            case 'm': $max *= 1024;
            case 'k': $max *= 1024;
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
        if (null !== $this->requestStack && null !== $request = $this->requestStack->getCurrentRequest()) {
            return $request->server->get('CONTENT_LENGTH');
        }

        return isset($_SERVER['CONTENT_LENGTH'])
            ? (int) $_SERVER['CONTENT_LENGTH']
            : null;
    }
}
