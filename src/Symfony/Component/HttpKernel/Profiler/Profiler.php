<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Profiler
{
    private $storage;
    private $collectors;
    private $logger;
    private $enabled;

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
        $this->collectors = array();
        $this->enabled = true;
    }

    /**
     * Disables the profiler.
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Loads the Profile for the given Response.
     *
     * @param Response $response A Response instance
     *
     * @return Profile A Profile instance
     */
    public function loadProfileFromResponse(Response $response)
    {
        if (!$token = $response->headers->get('X-Debug-Token')) {
            return false;
        }

        return $this->loadProfile($token);
    }

    /**
     * Loads the Profile for the given token.
     *
     * @param string $token A token
     *
     * @return Profile A Profile instance
     */
    public function loadProfile($token)
    {
        return $this->storage->read($token);
    }

    /**
     * Saves a Profile.
     *
     * @param Profile $profile A Profile instance
     *
     * @return Boolean
     */
    public function saveProfile(Profile $profile)
    {
        if (!($ret = $this->storage->write($profile)) && null !== $this->logger) {
            $this->logger->warn('Unable to store the profiler information.');
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

        $this->saveProfile($profile);

        return $profile;
    }

    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip     The IP
     * @param string $url    The URL
     * @param string $limit  The maximum number of tokens to return
     * @param string $method The request method
     *
     * @return array An array of tokens
     */
    public function find($ip, $url, $limit, $method)
    {
        return $this->storage->find($ip, $url, $limit, $method);
    }

    /**
     * Collects data for the given Response.
     *
     * @param Request    $request   A Request instance
     * @param Response   $response  A Response instance
     * @param \Exception $exception An exception instance if the request threw one
     *
     * @return Profile|null A Profile instance or null if the profiler is disabled
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (false === $this->enabled) {
            return;
        }

        $profile = new Profile(substr(sha1(uniqid(mt_rand(), true)), 0, 6));
        $profile->setTime(time());
        $profile->setUrl($request->getUri());
        $profile->setIp($request->getClientIp());
        $profile->setMethod($request->getMethod());

        $response->headers->set('X-Debug-Token', $profile->getToken());

        foreach ($this->collectors as $collector) {
            $collector->collect($request, $response, $exception);

            // forces collectors to become "read/only" (they loose their object dependencies)
            $profile->addCollector(unserialize(serialize($collector)));
        }

        return $profile;
    }

    /**
     * Gets the Collectors associated with this profiler.
     *
     * @return array An array of collectors
     */
    public function all()
    {
        return $this->collectors;
    }

    /**
     * Sets the Collectors associated with this profiler.
     *
     * @param array $collectors An array of collectors
     */
    public function set(array $collectors = array())
    {
        $this->collectors = array();
        foreach ($collectors as $collector) {
            $this->add($collector);
        }
    }

    /**
     * Adds a Collector.
     *
     * @param DataCollectorInterface $collector A DataCollectorInterface instance
     */
    public function add(DataCollectorInterface $collector)
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    /**
     * Returns true if a Collector for the given name exists.
     *
     * @param string $name A collector name
     *
     * @return Boolean
     */
    public function has($name)
    {
        return isset($this->collectors[$name]);
    }

    /**
     * Gets a Collector by name.
     *
     * @param string $name A collector name
     *
     * @return DataCollectorInterface A DataCollectorInterface instance
     *
     * @throws \InvalidArgumentException if the collector does not exist
     */
    public function get($name)
    {
        if (!isset($this->collectors[$name])) {
            throw new \InvalidArgumentException(sprintf('Collector "%s" does not exist.', $name));
        }

        return $this->collectors[$name];
    }
}
