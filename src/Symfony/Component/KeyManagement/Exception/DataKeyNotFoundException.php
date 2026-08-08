<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\Exception;

/**
 * Thrown when a payload refers to a data key that its store does not hold.
 *
 * Distinct from {@see KeyNotFoundException}, which is about a master key the KMS does not know:
 * here the KMS is fine, the row is missing. In practice it means the payload outlived its data
 * key, so the reference is reported in hexadecimal to make the row findable.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class DataKeyNotFoundException extends RuntimeException
{
    public function __construct(string $reference, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Data key "%s" was not found in the store.', bin2hex($reference)), 0, $previous);
    }
}
