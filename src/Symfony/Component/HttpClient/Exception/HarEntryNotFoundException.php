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

/**
 * Thrown when a HAR file does not contain an entry matching the request being replayed.
 */
class HarEntryNotFoundException extends TransportException
{
}
