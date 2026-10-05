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

use Symfony\Component\KeyManagement\Exception\RuntimeException;

/**
 * Represents an Operation Failed result returned by a KMIP server.
 *
 * The reason code is available for the bridge's exception mapping. Vendor reason codes above the signed integer range are hexadecimal strings on 32-bit PHP. The `hasOperation` flag records whether the response identified the failed operation, which the bridge requires before mapping a failure to a missing key or bad ciphertext. The server's Result Message is not included in the exception text.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class OperationFailed extends RuntimeException
{
    public function __construct(public readonly int|string $reason, public readonly bool $hasOperation)
    {
        parent::__construct('KMIP operation failed (reason '.(\is_int($reason) ? \sprintf('0x%X', $reason) : $reason).').');
    }
}
