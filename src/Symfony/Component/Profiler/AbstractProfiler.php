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

use Symfony\Component\Profiler\DataCollector\DataCollectorTrait;
use Symfony\Component\Profiler\DataCollector\RuntimeDataCollectorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Profiler\DataCollector\LateDataCollectorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Profiler\Storage\ProfilerStorageInterface;

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class AbstractProfiler
{

    use DataCollectorTrait;

    /**
     * @var ProfilerStorageInterface
     */
    private $storage;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var bool
     */
    private $enabled = true;

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
     * Loads the Profile for the given token.
     *
     * @param string $token A token
     *
     * @return Profile A Profile instance
     */
    public function load($token)
    {
        return $this->storage->read($token);
    }

    /**
     * Saves a Profile.
     *
     * @param Profile $profile A Profile instance
     *
     * @return bool
     */
    public function save(Profile $profile)
    {
        // late collect
        foreach ($profile->getCollectors() as $collector) {
            if ($collector instanceof \Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface ) {
                $collector->lateCollect();
            } else if ($collector instanceof LateDataCollectorInterface) {
                if ($profile->hasProfileData($collector->getName())) {
                    $collector->lateCollect();
                } else {
                    $profile->addProfileData($collector->getName(), $collector->lateCollect());
                }
                $profile->removeCollector($collector->getName());
            }
        }

        if (!($ret = $this->storage->write($profile)) && null !== $this->logger) {
            $this->logger->warning('Unable to store the profiler information.', array('configured_storage' => get_class($this->storage)));
        }

        return $ret;
    }

    /**
     * Purges all data from the storage.
     */
    public function purge()
    {
        $this->storage->purge();
    }

    /**
     * Exports the current profiler data.
     *
     * @param Profile $profile A Profile instance
     *
     * @return string The exported data
     */
    public function export(Profile $profile)
    {
        return base64_encode(serialize($profile));
    }

    /**
     * Imports data into the profiler storage.
     *
     * @param string $data A data string as exported by the export() method
     *
     * @return Profile A Profile instance
     */
    public function import($data)
    {
        $profile = unserialize(base64_decode($data));

        if ($this->storage->read($profile->getToken())) {
            return false;
        }

        $this->save($profile);

        return $profile;
    }

    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip     The IP
     * @param string $url    The URL
     * @param string $limit  The maximum number of tokens to return
     * @param string $method The request method
     * @param string $start  The start date to search from
     * @param string $end    The end date to search to
     *
     * @return array An array of tokens
     *
     * @see http://php.net/manual/en/datetime.formats.php for the supported date/time formats
     */
    public function find($ip, $url, $limit, $method, $start, $end)
    {
        return $this->storage->find($ip, $url, $limit, $method, $this->getTimestamp($start), $this->getTimestamp($end));
    }

    /**
     * Collects data
     * Keep parameters for BC reasons.
     *
     * @param Request    $request   A Request instance
     * @param Response   $response  A Response instance
     * @param \Exception $exception An exception instance if the request threw one
     *
     * @return Profile|null A Profile instance or null if the profiler is disabled
     */
    public function collect(Request $request = null, Response $response = null, \Exception $exception = null)
    {
        if (false === $this->enabled) {
            return;
        }

        $profile = $this->createProfile();

        foreach ($this->collectors as $collector) {
            $collector->setToken($profile->getToken());
            if ( $collector instanceof RuntimeDataCollectorInterface ) {
                $profile->addProfileData($collector->getName(), $collector->collect());
            } else if ( $collector instanceof LateDataCollectorInterface ) {
                // we need to clone for sub-requests
                $profile->addCollector(clone $collector);
            } else if (
                $collector instanceof \Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface
                && null !== $request
                && null !== $response
            ) {
                $collector->collect($request, $response, $exception);

                // we need to clone for sub-requests
                $profile->addCollector(clone $collector);
            }
        }

        return $profile;
    }

    /**
     * @return Profile
     */
    abstract protected function createProfile();

    private function getTimestamp($value)
    {
        if (null === $value || '' == $value) {
            return;
        }

        try {
            $value = new \DateTime(is_numeric($value) ? '@'.$value : $value);
        } catch (\Exception $e) {
            return;
        }

        return $value->getTimestamp();
    }
}
