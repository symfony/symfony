<?php

namespace Symfony\Component\HttpFoundation;

/**
 * Represents a cookie
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class Cookie
{
    protected $name;
    protected $value;
    protected $domain;
    protected $expire;
    protected $path;
    protected $secure;
    protected $httponly;

    public function __construct($name, $value = null, $expire = 0, $path = null, $domain = null, $secure = false, $httponly = true)
    {
        // from PHP source code
        if (preg_match("/[=,; \t\r\n\013\014]/", $name)) {
            throw new \InvalidArgumentException(sprintf('The cookie name "%s" contains invalid characters.', $name));
        }

        if (preg_match("/[,; \t\r\n\013\014]/", $value)) {
            throw new \InvalidArgumentException(sprintf('The cookie value "%s" contains invalid characters.', $name));
        }

        if (empty($name)) {
            throw new \InvalidArgumentException('The cookie name cannot be empty');
        }

        $this->name = $name;
        $this->value = $value;
        $this->domain = $domain;
        $this->expire = (integer) $expire;
        $this->path = $path;
        $this->secure = (Boolean) $secure;
        $this->httponly = (Boolean) $httponly;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function getExpire()
    {
        return $this->expire;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function isSecure()
    {
        return $this->secure;
    }

    public function isHttponly()
    {
        return $this->httponly;
    }

    /**
     * Whether this cookie is about to be cleared
     *
     * @return Boolean
     */
    public function isCleared()
    {
        return $this->expire < time();
    }
}