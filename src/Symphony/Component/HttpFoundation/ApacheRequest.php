<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpFoundation;

/**
 * Request represents an HTTP request from an Apache server.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ApacheRequest extends Request
{
    /**
     * {@inheritdoc}
     */
    protected function prepareRequestUri()
    {
        return $this->server->get('REQUEST_URI');
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareBaseUrl()
    {
        $baseUrl = $this->server->get('SCRIPT_NAME');

        if (false === strpos($this->server->get('REQUEST_URI'), $baseUrl)) {
            // assume mod_rewrite
            return rtrim(dirname($baseUrl), '/\\');
        }

        return $baseUrl;
    }
}
