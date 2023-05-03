<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Internal;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\StreamFactory;
use Http\Message\UriFactory;

if (interface_exists(RequestFactory::class)) {
    /**
     * @internal
     *
     * @deprecated since Symfony 6.3
     */
    interface LegacyHttplugInterface extends HttpClient, RequestFactory, StreamFactory, UriFactory
    {
    }
} else {
    /**
     * @internal
     *
     * @deprecated since Symfony 6.3
     */
    interface LegacyHttplugInterface extends HttpClient
    {
    }
}
