<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Transform;

use Doctrine\Persistence\Proxy;
use Symfony\Component\ObjectMapper\DepthAwareInterface;
use Symfony\Component\ObjectMapper\TransformCallableInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;

/**
 * Transforms an uninitialized proxy into null, optionally based on depth.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 *
 * @implements TransformCallableInterface<object,object>
 */
final class UninitializeProxy implements TransformCallableInterface, DepthAwareInterface
{
    private int $currentDepth = 0;

    /**
     * @param int $maxDepth a depth of 0 means it will *always* transform the proxy
     */
    public function __construct(
        private readonly int $maxDepth = 0,
    ) {
    }

    public function setDepth(int $depth): void
    {
        $this->currentDepth = $depth;
    }

    public function __invoke(mixed $value, object $source, ?object $target): mixed
    {
        if ($this->currentDepth >= $this->maxDepth) {
            if ($value instanceof LazyObjectInterface && !$value->isLazyObjectInitialized()) {
                return null;
            }

            if ($value instanceof Proxy && !$value->__isInitialized()) {
                return null;
            }
        }

        return $value;
    }
}
