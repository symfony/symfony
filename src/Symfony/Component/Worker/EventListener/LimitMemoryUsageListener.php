<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Worker\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Worker\Consumer\ConsumerEvents;
use Symfony\Component\Worker\Exception\StopException;
use Symfony\Component\Worker\Loop\LoopEvents;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class LimitMemoryUsageListener implements EventSubscriberInterface
{
    private $threshold;

    /**
     * @param int $threshold in bytes. Defaults to 100Mb
     */
    public function __construct($threshold = 104857600)
    {
        $this->threshold = $threshold;
    }

    public static function getSubscribedEvents()
    {
        return array(
            LoopEvents::SLEEP => 'limitMemoryUsage',
            ConsumerEvents::POST_CONSUME => 'limitMemoryUsage',
        );
    }

    /**
     * @throws StopException
     */
    public function limitMemoryUsage()
    {
        gc_collect_cycles();

        if ($this->threshold < memory_get_usage()) {
            throw new StopException(sprintf('Memory usage is too high (current: %s, limit: %s)', memory_get_usage(), $this->threshold));
        }
    }
}
