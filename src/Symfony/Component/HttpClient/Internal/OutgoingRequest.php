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

use Symfony\Component\HttpFoundation\Request;

/**
 * A request that describes a URL the application asked the client to fetch.
 *
 * The list of trusted hosts protects against host injection by remote clients.
 * It does not apply here: the host comes from the application itself.
 *
 * @internal
 */
final class OutgoingRequest extends Request
{
    public function getHost(): string
    {
        return strtolower(preg_replace('/:\d+$/', '', trim($this->headers->get('HOST', ''))));
    }
}
