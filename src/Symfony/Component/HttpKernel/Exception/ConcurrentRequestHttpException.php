<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Exception;

class ConcurrentRequestHttpException extends ConflictHttpException
{
    /**
     * @param string $key     The key of the lock held by the concurrent request
     * @param string $factory The name of the lock factory
     */
    public function __construct(
        public readonly string $key,
        public readonly string $factory,
        ?\Throwable $previous = null,
        int $code = 0,
        array $headers = [],
    ) {
        parent::__construct('A concurrent request is already being processed.', $previous, $code, $headers);
    }
}
