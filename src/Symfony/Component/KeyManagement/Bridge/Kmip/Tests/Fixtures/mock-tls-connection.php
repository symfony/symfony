<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Bridge\Kmip\Kmip;

use Symfony\Component\KeyManagement\Bridge\Kmip\Tests\DuplexKmipStream;

function stream_socket_client(string $address, ?int &$errorCode, ?string &$errorMessage, float $timeout, int $flags, $context)
{
    $errorCode = 0;
    $errorMessage = '';
    if (null !== DuplexKmipStream::$connectionWarning) {
        trigger_error(DuplexKmipStream::$connectionWarning, \E_USER_WARNING);

        return false;
    }
    DuplexKmipStream::$address = $address;
    DuplexKmipStream::$timeout = $timeout;
    DuplexKmipStream::$tlsOptions = stream_context_get_options($context)['ssl'];

    return fopen('kmip-duplex://response', 'w+');
}
