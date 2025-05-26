<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test\Traits;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

trait BrowserKitTrait
{
    private static function getResponse(): Response
    {
        trigger_deprecation('symfony/framework-bundle', '7.4', 'Use getClientResponse() instead.');
        return self::getClientResponse();
    }

    public static function getClientResponse(): Response
    {
        if (!$response = self::getClient()->getResponse()) {
            static::fail('A client must have an HTTP Response to make assertions. Did you forget to make an HTTP request?');
        }

        return $response;
    }

    private static function getRequest(): Request
    {
        trigger_deprecation('symfony/framework-bundle', '7.4', 'Use getClientRequest() instead.');
        return self::getClientRequest();
    }

    public static function getClientRequest(): Request
    {
        if (!$request = self::getClient()->getRequest()) {
            static::fail('A client must have an HTTP Request to make assertions. Did you forget to make an HTTP request?');
        }

        return $request;
    }
}
