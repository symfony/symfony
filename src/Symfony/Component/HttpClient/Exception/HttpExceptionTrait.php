<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Exception;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 *
 * @internal
 */
trait HttpExceptionTrait
{
    public function __construct(ResponseInterface $response)
    {
        $code = $response->getInfo('http_code');
        $url = $response->getInfo('url');
        $message = sprintf('HTTP %d returned for URL "%s".', $code, $url);

        foreach (array_reverse($response->getInfo('raw_headers')) as $h) {
            if (0 === strpos($h, 'HTTP/')) {
                $message = sprintf('%s returned for URL "%s".', $h, $url);
                break;
            }
        }

        parent::__construct($message, $code);
    }
}
