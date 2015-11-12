<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Profiler;

use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface;

/**
 * LoggerDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class LoggerDataCollector implements LateDataCollectorInterface
{
    private $logger;

    public function __construct($logger = null)
    {
        if (null !== $logger && $logger instanceof DebugLoggerInterface) {
            $this->logger = $logger;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        if (null !== $this->logger) {
            return new LoggerData($this->logger);
        }
    }
}
