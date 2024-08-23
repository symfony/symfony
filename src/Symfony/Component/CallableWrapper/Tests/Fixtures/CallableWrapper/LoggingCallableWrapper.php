<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CallableWrapper\Tests\Fixtures\CallableWrapper;

use Psr\Log\LoggerInterface;
use Symfony\Component\CallableWrapper\CallableWrapperInterface;

final readonly class LoggingCallableWrapper implements CallableWrapperInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function wrap(\Closure $func, Logging $logging = new Logging()): \Closure
    {
        return function (mixed ...$args) use ($func, $logging): mixed {
            $this->logger->log($logging->level, 'Before calling func', ['args' => \count($args)]);

            $result = $func(...$args);

            $this->logger->log($logging->level, 'After calling func', ['result' => $result]);

            return $result;
        };
    }
}
