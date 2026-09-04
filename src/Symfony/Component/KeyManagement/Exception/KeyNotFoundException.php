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
 * Thrown when the requested key cannot be located in the underlying KMS.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
class KeyNotFoundException extends RuntimeException
{
    public function __construct(string $keyId, ?\Throwable $previous = null)
    {
        parent::__construct(\sprintf('Key "%s" was not found in the KMS.', $keyId), 0, $previous);
    }
}
