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

@trigger_error('The '.__NAMESPACE__.'\Profile class is deprecated since Symfony 2.8 and will be removed in 3.0. Use Symfony\Component\Profiler\Profile instead.', E_USER_DEPRECATED);

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\Profiler\Profile as BaseProfile;

/**
 * Profile.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @deprecated Deprecated since Symfony 2.8, to be removed in Symfony 3.0.
 *             Use {@link Symfony\Component\Profiler\Profile} instead.
 */
class Profile extends BaseProfile
{
    private $token;

    /**
     * @var DataCollectorInterface[]
     */
    private $collectors = array();

    private $time;

    private $statusCode;

    private $ip;

    private $method;

    private $url;

    /**
     * Sets the token.
     *
     * @param string $token The token
     *
     * @deprecated since 2.8, Profile will be immutable in 3.0.
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    public function getToken()
    {
        if ( null === $this->token ) {
            return parent::getToken();
        }
        return $this->token;
    }

    /**
     * Sets the IP.
     *
     * @param string $ip
     *
     * @deprecated since 2.8, Profile will be immutable in 3.0.
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @deprecated since 2.8.
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * Sets the Method.
     *
     * @param string $method
     *
     * @deprecated since 2.8, Profile will be immutable in 3.0.
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @deprecated since 2.8.
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * Sets the URL.
     *
     * @param string $url
     *
     * @deprecated since 2.8, Profile will be immutable in 3.0.
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @deprecated since 2.8.
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Sets the time.
     *
     * @param int $time
     *
     * @deprecated since 2.8, Profile will be immutable in 3.0.
     */
    public function setTime($time)
    {
        $this->time = $time;
    }

    public function getTime()
    {
        if ( null === $this->time ) {
            return parent::getTime();
        }
        return $this->time;
    }

    /**
     * Sets the StatusCode.
     *
     * @param int $statusCode
     *
     * @deprecated since 2.8, Profile will be immutable in 3.0.
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return int
     *
     * @deprecated since 2.8, use Profile::getIndex($name).
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Sets the Collectors associated with this profile.
     *
     * @param DataCollectorInterface[] $collectors
     */
    public function setCollectors(array $collectors)
    {
        $this->collectors = array();
        foreach ($collectors as $collector) {
            $this->addCollector($collector);
        }
    }

    /**
     * Gets the Collectors associated with this profile.
     *
     * @return DataCollectorInterface[]
     */
    public function getCollectors()
    {
        return $this->collectors;
    }

    /**
     * Adds a Collector.
     *
     * @param DataCollectorInterface $collector A DataCollectorInterface instance
     */
    public function addCollector(DataCollectorInterface $dataCollector)
    {
        $this->collectors[$dataCollector->getName()] = $dataCollector;
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
    public function getCollector($name)
    {
        if (!isset($this->collectors[$name])) {
            throw new \InvalidArgumentException(sprintf('Collector "%s" does not exist.', $name));
        }

        return $this->collectors[$name];
    }

    /**
     * Returns true if a Collector for the given name exists.
     *
     * @param string $name A collector name
     *
     * @return bool
     */
    public function hasCollector($name)
    {
        return isset($this->collectors[$name]);
    }

    public function getIndexes() {
        return array(
            'ip' => $this->ip,
            'url' => $this->url,
            'status_code' => $this->statusCode,
            'method' => $this->method,
        );
    }

    public function getIndex($name)
    {
        if ( !isset($this->$name) ) {
            return;
        }
        return $this->$name;
    }
}
