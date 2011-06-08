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
            if (0 === strpos($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            }

            // CONTENT_* are not prefixed with HTTP_
            if (in_array($key, array('CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'))) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }
}
