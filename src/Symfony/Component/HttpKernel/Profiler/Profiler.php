<?php

namespace Symfony\Component\HttpKernel\Profiler;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\ProfilerStorageInterface;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\Log\LoggerInterface;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Profiler
{
    protected $storage;
    protected $collectors;
    protected $logger;
    protected $enabled;
    protected $token;
    protected $data;
    protected $ip;
    protected $url;
    protected $time;
    protected $empty;

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
        $this->empty = true;
    }

    /**
     * Disables the profiler.
     */
    public function disable()
    {
        $this->enabled = false;
    }

    /**
     * Loads a Profiler for the given Response.
     *
     * @param Response $response A Response instance
     *
     * @return Profiler A new Profiler instance
     */
    public function loadFromResponse(Response $response)
    {
        if (!$token = $response->headers->get('X-Debug-Token')) {
            return null;
        }

        return $this->loadFromToken($token);
    }

    /**
     * Loads a Profiler for the given token.
     *
     * @param string $token A token
     *
     * @return Profiler A new Profiler instance
     */
    public function loadFromToken($token)
    {
        $profiler = new self($this->storage, $this->logger);
        $profiler->setToken($token);

        return $profiler;
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
     * @return string The exported data
     */
    public function export()
    {
        $unpack = unpack('H*', serialize(array($this->token, $this->collectors, $this->ip, $this->url, $this->time)));

        return $unpack[1];
    }

    /**
     * Imports data into the profiler storage.
     *
     * @param string $data A data string as exported by the export() method
     *
     * @return string The token associated with the imported data
     */
    public function import($data)
    {
        list($token, $collectors, $ip, $url, $time) = unserialize(pack('H*', $data));

        if (false !== $this->storage->read($token)) {
            return false;
        }

        $unpack = unpack('H*', serialize($this->collectors));

        $this->storage->write($token, $unpack[1], $ip, $url, $time);

        return $token;
    }

    /**
     * Sets the token.
     *
     * @param string $token The token
     */
    public function setToken($token)
    {
        $this->token = $token;

        if (false !== $items = $this->storage->read($token)) {
            list($data, $this->ip, $this->url, $this->time) = $items;
            $this->set(unserialize(pack('H*', $data)));

            $this->empty = false;
        } else {
            $this->empty = true;
        }
    }

    /**
     * Gets the token.
     *
     * @return string The token
     */
    public function getToken()
    {
        if (null === $this->token) {
            $this->token = uniqid();
        }

        return $this->token;
    }

    /**
     * Checks if the profiler is empty.
     *
     * @return Boolean Whether the profiler is empty or not
     */
    public function isEmpty()
    {
        return $this->empty;
    }

    /**
     * Returns the IP.
     *
     * @return string The IP
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Returns the URL.
     *
     * @return string The URL
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Returns the time.
     *
     * @return string The time
     */
    public function getTime()
    {
        return $this->time;
    }

    /**
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip    The IP
     * @param string $url   The URL
     * @param string $limit The maximum number of tokens to return
     *
     * @return array An array of tokens
     */
    public function find($ip, $url, $limit)
    {
        return $this->storage->find($ip, $url, $limit);
    }

    /**
     * Collects data for the given Response.
     *
     * @param Request    $request   A Request instance
     * @param Response   $response  A Response instance
     * @param \Exception $exception An exception instance if the request threw one
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        if (false === $this->enabled) {
            return;
        }

        $response = $response;
        $response->headers->set('X-Debug-Token', $this->getToken());

        foreach ($this->collectors as $collector) {
            $collector->collect($request, $response, $exception);
        }

        $this->ip   = $request->server->get('REMOTE_ADDR');
        $this->url  = $request->getUri();
        $this->time = time();

        $unpack = unpack('H*', serialize($this->collectors));
        try {
            $this->storage->write($this->token, $unpack[1], $this->ip, $this->url, $this->time);
            $this->empty = false;
        } catch (\Exception $e) {
            if (null !== $this->logger) {
                $this->logger->err(sprintf('Unable to store the profiler information (%s).', $e->getMessage()));
            }
        }
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
