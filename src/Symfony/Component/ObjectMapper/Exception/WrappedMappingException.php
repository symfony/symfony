<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Exception;

/**
 * @experimental
 *
 * @author Martin Komischke <martin.komischke@gmail.com>
 */
final class WrappedMappingException extends RuntimeException
{
    /**
     * @param array<\Throwable> $exceptions The collection of exceptions reveals which discrete mapping has failed.
     */
    public function __construct(string $message, public readonly array $exceptions)
    {
        parent::__construct($message);
    }
}
