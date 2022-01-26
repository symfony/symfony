<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Attribute;

/**
 * The Cache class handles the Cache attribute parts.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
class Cache
{
    /**
     * The expiration date as a valid date for the strtotime() function.
     *
     * @var string
     */
    private $expires;

    /**
     * The number of seconds that the response is considered fresh by a private
     * cache like a web browser.
     *
     * @var int|string|null
     */
    private $maxage;

    /**
     * The number of seconds that the response is considered fresh by a public
     * cache like a reverse proxy cache.
     *
     * @var int|string|null
     */
    private $smaxage;

    /**
     * Whether the response is public or not.
     *
     * @var bool
     */
    private $public;

    /**
     * Whether or not the response must be revalidated.
     *
     * @var bool
     */
    private $mustRevalidate;

    /**
     * Additional "Vary:"-headers.
     *
     * @var array
     */
    private $vary;

    /**
     * An expression to compute the Last-Modified HTTP header.
     *
     * @var string
     */
    private $lastModified;

    /**
     * An expression to compute the ETag HTTP header.
     *
     * @var string
     */
    private $etag;

    /**
     * max-stale Cache-Control header
     * It can be expressed in seconds or with a relative time format (1 day, 2 weeks, ...).
     *
     * @var int|string
     */
    private $maxStale;

    /**
     * stale-while-revalidate Cache-Control header
     * It can be expressed in seconds or with a relative time format (1 day, 2 weeks, ...).
     *
     * @var int|string
     */
    private $staleWhileRevalidate;

    /**
     * stale-if-error Cache-Control header
     * It can be expressed in seconds or with a relative time format (1 day, 2 weeks, ...).
     *
     * @var int|string
     */
    private $staleIfError;

    /**
     * @param int|string|null $maxage
     * @param int|string|null $smaxage
     * @param int|string|null $maxstale
     * @param int|string|null $staleWhileRevalidate
     * @param int|string|null $staleIfError
     */
    public function __construct(
        string $expires = null,
        $maxage = null,
        $smaxage = null,
        bool $public = null,
        bool $mustRevalidate = null,
        array $vary = null,
        string $lastModified = null,
        string $Etag = null,
        $maxstale = null,
        $staleWhileRevalidate = null,
        $staleIfError = null
    ) {
        $this->expires = $expires;
        $this->maxage = $maxage;
        $this->smaxage = $smaxage;
        $this->public = $public;
        $this->mustRevalidate = $mustRevalidate;
        $this->vary = $vary;
        $this->lastModified = $lastModified;
        $this->etag = $Etag;
        $this->maxStale = $maxstale;
        $this->staleWhileRevalidate = $staleWhileRevalidate;
        $this->staleIfError = $staleIfError;
    }

    /**
     * Returns the expiration date for the Expires header field.
     *
     * @return string
     */
    public function getExpires()
    {
        return $this->expires;
    }

    /**
     * Sets the expiration date for the Expires header field.
     *
     * @param string $expires A valid php date
     */
    public function setExpires($expires)
    {
        $this->expires = $expires;
    }

    /**
     * Sets the number of seconds for the max-age cache-control header field.
     *
     * @param int $maxage A number of seconds
     */
    public function setMaxAge($maxage)
    {
        $this->maxage = $maxage;
    }

    /**
     * Returns the number of seconds the response is considered fresh by a
     * private cache.
     *
     * @return int
     */
    public function getMaxAge()
    {
        return $this->maxage;
    }

    /**
     * Sets the number of seconds for the s-maxage cache-control header field.
     *
     * @param int $smaxage A number of seconds
     */
    public function setSMaxAge($smaxage)
    {
        $this->smaxage = $smaxage;
    }

    /**
     * Returns the number of seconds the response is considered fresh by a
     * public cache.
     *
     * @return int
     */
    public function getSMaxAge()
    {
        return $this->smaxage;
    }

    /**
     * Returns whether or not a response is public.
     *
     * @return bool
     */
    public function isPublic()
    {
        return true === $this->public;
    }

    /**
     * @return bool
     */
    public function mustRevalidate()
    {
        return true === $this->mustRevalidate;
    }

    /**
     * Forces a response to be revalidated.
     *
     * @param bool $mustRevalidate
     */
    public function setMustRevalidate($mustRevalidate)
    {
        $this->mustRevalidate = (bool) $mustRevalidate;
    }

    /**
     * Returns whether or not a response is private.
     *
     * @return bool
     */
    public function isPrivate()
    {
        return false === $this->public;
    }

    /**
     * Sets a response public.
     *
     * @param bool $public A boolean value
     */
    public function setPublic($public)
    {
        $this->public = (bool) $public;
    }

    /**
     * Returns the custom "Vary"-headers.
     *
     * @return array
     */
    public function getVary()
    {
        return $this->vary;
    }

    /**
     * Add additional "Vary:"-headers.
     *
     * @param array $vary
     */
    public function setVary($vary)
    {
        $this->vary = $vary;
    }

    /**
     * Sets the "Last-Modified"-header expression.
     *
     * @param string $expression
     */
    public function setLastModified($expression)
    {
        $this->lastModified = $expression;
    }

    /**
     * Returns the "Last-Modified"-header expression.
     *
     * @return string
     */
    public function getLastModified()
    {
        return $this->lastModified;
    }

    /**
     * Sets the "ETag"-header expression.
     *
     * @param string $expression
     */
    public function setEtag($expression)
    {
        $this->etag = $expression;
    }

    /**
     * Returns the "ETag"-header expression.
     *
     * @return string
     */
    public function getEtag()
    {
        return $this->etag;
    }

    /**
     * @return int|string
     */
    public function getMaxStale()
    {
        return $this->maxStale;
    }

    /**
     * Sets the number of seconds for the max-stale cache-control header field.
     *
     * @param int|string $maxStale A number of seconds
     */
    public function setMaxStale($maxStale)
    {
        $this->maxStale = $maxStale;
    }

    /**
     * @return int|string
     */
    public function getStaleWhileRevalidate()
    {
        return $this->staleWhileRevalidate;
    }

    /**
     * @param int|string $staleWhileRevalidate
     *
     * @return self
     */
    public function setStaleWhileRevalidate($staleWhileRevalidate)
    {
        $this->staleWhileRevalidate = $staleWhileRevalidate;

        return $this;
    }

    /**
     * @return int|string
     */
    public function getStaleIfError()
    {
        return $this->staleIfError;
    }

    /**
     * @param int|string $staleIfError
     *
     * @return self
     */
    public function setStaleIfError($staleIfError)
    {
        $this->staleIfError = $staleIfError;

        return $this;
    }
}
