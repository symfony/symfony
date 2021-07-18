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
    private $collectors = [];

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
    private $children = [];

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function setToken(string $token)
    {
        $this->token = $token;
    }

    /**
     * Gets the token.
     *
     * @return string The token
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Sets the parent token.
     */
    public function setParent(self $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the parent profile.
     *
     * @return self
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Returns the parent token.
     *
     * @return string|null The parent token
     */
    public function getParentToken()
    {
        return $this->parent ? $this->parent->getToken() : null;
    }

    /**
     * Returns the IP.
     *
     * @return string|null The IP
     */
    public function getIp()
    {
        return $this->ip;
    }

    public function setIp(?string $ip)
    {
        $this->ip = $ip;
    }

    /**
     * Returns the request method.
     *
     * @return string|null The request method
     */
    public function getMethod()
    {
        return $this->method;
    }

    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    /**
     * Returns the URL.
     *
     * @return string|null The URL
     */
    public function getUrl()
    {
        return $this->url;
    }

    public function setUrl(?string $url)
    {
        $this->url = $url;
    }

    /**
     * @return int The time
     */
    public function getTime()
    {
        return $this->time ?? 0;
    }

    public function setTime(int $time)
    {
        $this->time = $time;
    }

    public function setStatusCode(int $statusCode)
    {
        $this->statusCode = $statusCode;
    }

    /**
     * @return int|null
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * Finds children profilers.
     *
     * @return self[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * Sets children profiler.
     *
     * @param Profile[] $children
     */
    public function setChildren(array $children)
    {
        $this->children = [];
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
     * @return DataCollectorInterface A DataCollectorInterface instance
     *
     * @throws \InvalidArgumentException if the collector does not exist
     */
    public function getCollector(string $name)
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
        $this->collectors = [];
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
     * @return bool
     */
    public function hasCollector(string $name)
    {
        return isset($this->collectors[$name]);
    }

    /**
     * @return array
     */
    public function __sleep()
    {
        return ['token', 'parent', 'children', 'collectors', 'ip', 'method', 'url', 'time', 'statusCode'];
    }
}
