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
class Profile implements \Serializable
{
    private $token;
    private $collectors;
    private $ip;
    private $url;
    private $time;
    private $parent;
    private $children;

    public function __construct($token)
    {
        $this->token = $token;
        $this->collectors = array();
        $this->children = array();
    }

    /**
     * Sets the token.
     *
     * @param string $token The token
     */
    public function setToken($token)
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
     * Sets the parent token
     *
     * @param Profile $parent The parent Profile
     */
    public function setParent(Profile $parent)
    {
        $this->parent = $parent;
    }

    /**
     * Returns the parent token.
     *
     * @return Profile The parent profile
     */
    public function getParent()
    {
        return $this->parent;
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

    public function setIp($ip)
    {
        $this->ip = $ip;
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

    public function setUrl($url)
    {
        $this->url = $url;
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

    public function setTime($time)
    {
        $this->time = $time;
    }

    /**
     * Finds children profilers.
     *
     * @return array An array of Profile
     */
    public function getChildren()
    {
        return $this->children;
    }

    public function setChildren(array $children)
    {
        $this->children = array();
        foreach ($children as $child) {
            $this->addChild($child);
        }
    }

    public function addChild(Profile $child)
    {
        $this->children[] = $child;
    }

    public function getCollector($name)
    {
        if (!isset($this->collectors[$name])) {
            throw new \InvalidArgumentException(sprintf('Collector "%s" does not exist.', $name));
        }

        return $this->collectors[$name];
    }

    public function getCollectors()
    {
        return $this->collectors;
    }

    public function setCollectors(array $collectors)
    {
        $this->collectors = array();
        foreach ($collectors as $collector) {
            $this->addCollector($collector);
        }
    }

    public function addCollector(DataCollectorInterface $collector)
    {
        $this->collectors[$collector->getName()] = $collector;
    }

    public function hasCollector($name)
    {
        return isset($this->collectors[$name]);
    }

    public function serialize()
    {
        return serialize(array($this->token, $this->parent, $this->children, $this->collectors, $this->ip, $this->url, $this->time));
    }

    public function unserialize($data)
    {
        list($this->token, $this->parent, $this->children, $this->collectors, $this->ip, $this->url, $this->time) = unserialize($data);
    }
}
