<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Decorator\Tests\Fixtures\Decorator;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\Decorator\DecoratorInterface;

final readonly class LoggingDecorator implements DecoratorInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function decorate(\Closure $func, Logging $logging = new Logging()): \Closure
    {
        return function (mixed ...$args) use ($func, $logging): mixed {
            $this->logger->log($logging->level, 'Before calling func', ['args' => count($args)]);

            $result = $func(...$args);

            $this->logger->log($logging->level, 'After calling func', ['result' => $result]);

            return $result;
        };
    }
}
