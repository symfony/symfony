<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * HeaderBag is a container for HTTP headers.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Bulat Shakirzyanov <mallluhuct@gmail.com>
 */
class ServerBag extends ParameterBag
{
    public function getHeaders()
    {
        $headers = array();
        foreach ($this->parameters as $key => $value) {
            if ('HTTP_' === substr($key, 0, 5)) {
                $headers[substr($key, 5)] = $value;
            }
        }

        // CONTENT_TYPE and CONTENT_LENGTH are not prefixed with HTTP_
        foreach (array('CONTENT_TYPE', 'CONTENT_LENGTH') as $key) {
            if (isset($this->parameters[$key])) {
                $headers[$key] = $this->parameters[$key];
            }
        }

        // PHP_AUTH_USER/PHP_AUTH_PW
        if (isset($this->parameters['PHP_AUTH_USER'])) {
            $pass = isset($this->parameters['PHP_AUTH_PW']) ? $this->parameters['PHP_AUTH_PW'] : '';
            $headers['AUTHORIZATION'] = 'Basic '.base64_encode($this->parameters['PHP_AUTH_USER'].':'.$pass);
        }

        return $headers;
    }
}
