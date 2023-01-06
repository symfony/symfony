<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Middleware\Debug;

use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Middleware to collect debug data.
 *
 * @author Laurent VOULLEMIER <laurent.voullemier@gmail.com>
 */
final class Middleware implements MiddlewareInterface
{
    public function __construct(
        private DebugDataHolder $debugDataHolder,
        private ?Stopwatch $stopwatch,
        private string $connectionName = 'default',
    ) {
    }

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new Driver($driver, $this->debugDataHolder, $this->stopwatch, $this->connectionName);
    }
}
