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
     * Returns the "post_max_size" ini setting.
     *
     * @return string The value of the ini setting.
     */
    public function getPostMaxSize()
    {
        return ini_get('post_max_size');
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
