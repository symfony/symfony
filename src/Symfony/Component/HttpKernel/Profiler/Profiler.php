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

use Psr\Log\LoggerInterface;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Profiler.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Profiler implements ResetInterface
{
    private $storage;

    /**
     * @var DataCollectorInterface[]
     */
    private $collectors = [];

    private $logger;
    private $initiallyEnabled = true;
    private $enabled = true;

    public function __construct(ProfilerStorageInterface $storage, LoggerInterface $logger = null, bool $enable = true)
    {
        $this->storage = $storage;
        $this->logger = $logger;
        $this->initiallyEnabled = $this->enabled = $enable;
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
     * Loads the Profile for the given Response.
     *
     * @return Profile|null A Profile instance
     */
    public function loadProfileFromResponse(Response $response)
    {
        if (!$token = $response->headers->get('X-Debug-Token')) {
            return null;
        }

        return $this->loadProfile($token);
    }

    /**
     * Loads the Profile for the given token.
     *
     * @param string $token A token
     *
     * @return Profile|null A Profile instance
     */
    public function loadProfile($token)
    {
        return $this->storage->read($token);
    }

    /**
     * Saves a Profile.
     *
     * @return bool
     */
    public function saveProfile(Profile $profile)
    {
        // late collect
        foreach ($profile->getCollectors() as $collector) {
            if ($collector instanceof LateDataCollectorInterface) {
                $collector->lateCollect();
            }
        }

        if (!($ret = $this->storage->write($profile)) && null !== $this->logger) {
            $this->logger->warning('Unable to store the profiler information.', ['configured_storage' => \get_class($this->storage)]);
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
     * Finds profiler tokens for the given criteria.
     *
     * @param string $ip         The IP
     * @param string $url        The URL
     * @param string $limit      The maximum number of tokens to return
     * @param string $method     The request method
     * @param string $start      The start date to search from
     * @param string $end        The end date to search to
     * @param string $statusCode The request status code
     *
     * @return array An array of tokens
     *
     * @see https://php.net/datetime.formats for the supported date/time formats
     */
    public function find($ip, $url, $limit, $method, $start, $end, $statusCode = null)
    {
        return $this->storage->find($ip, $url, $limit, $method, $this->getTimestamp($start), $this->getTimestamp($end), $statusCode);
    }

    /**
     * Collects data for the given Response.
     *
     * @param \Throwable|null $exception
     *
     * @return Profile|null A Profile instance or null if the profiler is disabled
     */
    public function collect(Request $request, Response $response/* , \Throwable $exception = null */)
    {
        $exception = 2 < \func_num_args() ? func_get_arg(2) : null;

        if (false === $this->enabled) {
            return null;
        }

        $profile = new Profile(substr(hash('sha256', uniqid(mt_rand(), true)), 0, 6));
        $profile->setTime(time());
        $profile->setUrl($request->getUri());
        $profile->setMethod($request->getMethod());
        $profile->setStatusCode($response->getStatusCode());
        try {
            $profile->setIp($request->getClientIp());
        } catch (ConflictingHeadersException $e) {
            $profile->setIp('Unknown');
        }

        if ($prevToken = $response->headers->get('X-Debug-Token')) {
            $response->headers->set('X-Previous-Debug-Token', $prevToken);
        }

        $response->headers->set('X-Debug-Token', $profile->getToken());

        $wrappedException = null;
        foreach ($this->collectors as $collector) {
            if (($e = $exception) instanceof \Error) {
                $r = new \ReflectionMethod($collector, 'collect');
                $e = 2 >= $r->getNumberOfParameters() || !($p = $r->getParameters()[2])->hasType() || \Exception::class !== $p->getType()->getName() ? $e : ($wrappedException ?? $wrappedException = new FatalThrowableError($e));
            }

            $collector->collect($request, $response, $e);
            // we need to clone for sub-requests
            $profile->addCollector(clone $collector);
        }

        return $profile;
    }

    public function reset()
    {
        foreach ($this->collectors as $collector) {
            $collector->reset();
        }
        $this->enabled = $this->initiallyEnabled;
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
     * @param DataCollectorInterface[] $collectors An array of collectors
     */
    public function set(array $collectors = [])
    {
        $this->collectors = [];
        foreach ($collectors as $collector) {
            $this->add($collector);
        }
    }

    /**
     * Adds a Collector.
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
     * @return bool
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

    private function getTimestamp(?string $value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        try {
            $value = new \DateTime(is_numeric($value) ? '@'.$value : $value);
        } catch (\Exception $e) {
            return null;
        }

        return $value->getTimestamp();
    }
}
