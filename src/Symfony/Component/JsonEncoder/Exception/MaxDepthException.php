<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Exception;

/**
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @experimental
 */
final class MaxDepthException extends RuntimeException
{
    /**
     * @param class-string $className
     */
    public function __construct(string $className, int $limit)
    {
        parent::__construct(sprintf('Max depth has been reached for class "%s" (configured limit: %d).', $className, $limit));
    }
}
