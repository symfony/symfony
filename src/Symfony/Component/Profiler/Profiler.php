<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler;

use Symfony\Component\Profiler\ProfileData\GenericProfileData;
use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Profiler\Storage\ProfilerStorageInterface;

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class Profiler
{
    /**
     * @var DataCollectorInterface[]
     */
    protected $collectors = array();

    /**
     * @var ProfilerStorageInterface
     */
    protected $storage;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var bool
     */
    protected $enabled = true;

    /**
     * Constructor.
     *
     * @param ProfilerStorageInterface $storage A ProfilerStorageInterface instance
     * @param LoggerInterface          $logger  A LoggerInterface instance
     */
    public function __construct(ProfilerStorageInterface $storage, LoggerInterface $logger = null)
    {
        $this->storage = $storage;
        $this->logger = $logger;
    }

    /**
     * Disables the profiler.
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Enables the profiler.
     */
    public function enable()
    {
        $this->enabled = true;
    }

    /**
     * Saves a Profile.
     *
     * @param Profile $profile A Profile instance
     *
     * @return bool
     */
    public function save(Profile $profile, array $indexes)
    {
        $dataCollectors = array_filter($this->collectors, function(DataCollectorInterface $dataCollector) {
            return ($dataCollector instanceof LateDataCollectorInterface);
        });

        foreach ( $dataCollectors as $collector) {
            if (!method_exists($collector, 'getCollectedData')) {
                $profile->add(new GenericProfileData($collector));
            } else {
                $profile->add($collector->getCollectedData());
            }
        }

        if (!($ret = $this->storage->write($profile, $indexes)) && null !== $this->logger) {
            $this->logger->warning('Unable to store the profiler information.', array('configured_storage' => get_class($this->storage)));
        }

        return $ret;
    }

    /**
     * Collects data.
     *
     * @return Profile|void
     */
    public function profile()
    {
        if (!$this->enabled) {
            return;
        }

        $profile = new Profile(substr(hash('sha256', uniqid(mt_rand(), true)), 0, 6));

        $dataCollectors = array_filter($this->collectors, function(DataCollectorInterface $dataCollector) {
            return !($dataCollector instanceof LateDataCollectorInterface);
        });

        foreach ( $dataCollectors as $collector) {
            if (method_exists($collector, 'getCollectedData')) {
                $profile->add($collector->getCollectedData());
            }
        }

        return $profile;
    }

    /**
     * Adds a Collector.
     *
     * @param DataCollectorInterface $collector A DataCollectorInterface instance
     */
    public function add(DataCollectorInterface $collector)
    {
        $this->collectors[] = $collector;
    }

    /**
     * @return \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface[]
     */
    public function getDeprecatedDataCollectors()
    {
        return array_map(function($collector) {
                return clone $collector;
            },
            array_filter($this->collectors, function($collector) {
                return $collector instanceof \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
            })
        );
    }
}
