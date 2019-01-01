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

use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;

/**
 * Profile.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Profile
{
    private $token;

    /**
     * @var DataCollectorInterface[]
     */
    private $collectors = array();

    private $ip;
    private $method;
    private $url;
    private $time;
    private $statusCode;

    /**
     * @var Profile
     */
    private $parent;

    /**
     * @var Profile[]
     */
    private $children = array();

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * @param string $token The token
     */
    public function setToken($token)
    {
        $this->token = $token;
    }

    /**
     * @return string The token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * @param self
     */
    public function setParent(self $parent)
    {
        $this->parent = $parent;
    }

    /**
     * @return self
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * @return string|null The parent token
     */
    public function getParentToken()
    {
        return $this->parent ? $this->parent->getToken() : null;
    }

    /**
     * @return string The IP
     */
    public function getIp()
    {
        return $this->ip;
    }

    /**
     * @param string $ip
     */
    public function setIp($ip)
    {
        $this->ip = $ip;
    }

    /**
     * @return string The request method
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param string $method The request method
     */
    public function setMethod($method)
    {
        $this->method = $method;
    }

    /**
     * @return string The URL
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param string $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @return int The time
     */
    public function getTime()
    {
        if (null === $this->time) {
            return 0;
        }

        return $this->time;
    }

    /**
     * @param int $time The time
     */
    public function setTime($time)
    {
        $this->time = $time;
    }

    /**
     * @param int $statusCode
     */
    public function setStatusCode($statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return self[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param Profile[] $children
     */
    public function setChildren(array $children)
    {
        $this->children = array();
        foreach ($children as $child) {
            $this->addChild($child);
        }
    }

    /**
     * Adds the child token.
     */
    public function addChild(self $child)
    {
        $this->children[] = $child;
        $child->setParent($this);
    }

    public function getChildByToken(string $token): ?self
    {
        foreach ($this->children as $child) {
            if ($token === $child->getToken()) {
                return $child;
            }
        }

        return null;
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
     * Gets the Collectors associated with this profile.
     *
     * @return DataCollectorInterface[]
     */
    public function getCollectors()
    {
        return $this->collectors;
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
     * Adds a Collector.
     */
    public function addCollector(DataCollectorInterface $collector)
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
    public function hasCollector($name)
    {
        return isset($this->collectors[$name]);
    }

    public function __sleep()
    {
        return array('token', 'parent', 'children', 'collectors', 'ip', 'method', 'url', 'time', 'statusCode');
    }
}
