<?php

namespace Symfony\Components\HttpFoundation;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * CacheControl is a wrapper for the Cache-Control HTTP header.
 *
 * This class knows about allowed attributes
 * (and those that only apply to requests or responses).
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class CacheControl
{
    protected $bag;
    protected $attributes;
    protected $type;

    /**
     * Constructor.
     *
     * @param HeaderBag $bag    A HeaderBag instance
     * @param string    $header The value of the Cache-Control HTTP header
     * @param string    $type   The type (null, request, or response)
     */
    public function __construct(HeaderBag $bag, $header, $type = null)
    {
        if (null !== $type && !in_array($type, array('request', 'response'))) {
            throw new \InvalidArgumentException(sprintf('The "%s" type is not supported by the CacheControl constructor.', $type));
        }
        $this->type = $type;

        $this->bag = $bag;
        $this->attributes = $this->parse($header);
    }

    public function __toString()
    {
        $parts = array();
        ksort($this->attributes);
        foreach ($this->attributes as $key => $value) {
            if (true === $value) {
                $parts[] = $key;
            } else {
                if (preg_match('#[^a-zA-Z0-9._-]#', $value)) {
                    $value = '"'.$value.'"';
                }

                $parts[] = "$key=$value";
            }
        }

        return implode(',', $parts);
    }

    public function getMaxStale()
    {
        $this->checkAttribute('max-stale', 'request');

        return array_key_exists('max-stale', $this->attributes) ? $this->attributes['max-stale'] : false;
    }

    public function setMaxStale($value)
    {
        $this->checkAttribute('max-stale', 'request');

        $this->setValue('max-stale', $value);
    }

    public function getMinFresh()
    {
        $this->checkAttribute('min-fresh', 'request');

        return array_key_exists('min-fresh', $this->attributes) ? $this->attributes['min-fresh'] : false;
    }

    public function setMinFresh($value)
    {
        $this->checkAttribute('min-fresh', 'request');

        $this->setValue('min-fresh', $value);
    }

    public function isOnlyIfCached()
    {
        $this->checkAttribute('only-if-cached', 'request');

        return array_key_exists('only-if-cached', $this->attributes);
    }

    public function setOnlyIfCached($value)
    {
        $this->checkAttribute('only-if-cached', 'request');

        $this->setValue('only-if-cached', $value, true);
    }

    public function isPublic()
    {
        $this->checkAttribute('public', 'response');

        return array_key_exists('public', $this->attributes);
    }

    public function setPublic($value)
    {
        $this->checkAttribute('public', 'response');

        $this->setValue('public', $value, true);
    }

    public function isPrivate()
    {
        $this->checkAttribute('private', 'response');

        return array_key_exists('private', $this->attributes);
    }

    public function getPrivate()
    {
        $this->checkAttribute('private', 'response');

        return array_key_exists('private', $this->attributes) ? $this->attributes['private'] : false;
    }

    public function setPrivate($value)
    {
        $this->checkAttribute('private', 'response');

        $this->setValue('private', $value, true);
    }

    public function isNoCache()
    {
        return array_key_exists('no-cache', $this->attributes);
    }

    public function getNoCache()
    {
        return array_key_exists('no-cache', $this->attributes) ? $this->attributes['no-cache'] : false;
    }

    public function setNoCache($value)
    {
        $this->setValue('no-cache', $value, true);
    }

    public function isNoStore()
    {
        return array_key_exists('no-store', $this->attributes);
    }

    public function setNoStore($value)
    {
        $this->setValue('no-store', $value, true);
    }

    public function isNoTransform()
    {
        return array_key_exists('no-tranform', $this->attributes);
    }

    public function setNoTransform($value)
    {
        $this->setValue('no-transform', $value, true);
    }

    public function getMaxAge()
    {
        return array_key_exists('max-age', $this->attributes) ? $this->attributes['max-age'] : null;
    }

    public function setMaxAge($age)
    {
        $this->setValue('max-age', (integer) $age);
    }

    public function getSharedMaxAge()
    {
        $this->checkAttribute('s-maxage', 'response');

        return array_key_exists('s-maxage', $this->attributes) ? $this->attributes['s-maxage'] : null;
    }

    public function setSharedMaxAge($age)
    {
        $this->checkAttribute('s-maxage', 'response');

        $this->setValue('s-maxage', (integer) $age);
    }

    public function setStaleWhileRevalidate($age)
    {
        $this->checkAttribute('stale-while-revalidate', 'response');

        $this->setValue('stale-while-revalidate', (integer) $age);
    }

    public function getStaleWhileRevalidate()
    {
        $this->checkAttribute('stale-while-revalidate', 'response');

        return array_key_exists('stale-while-revalidate', $this->attributes) ? $this->attributes['stale-while-revalidate'] : null;
    }

    public function setStaleIfError($age)
    {
        $this->setValue('stale-if-error', (integer) $age);
    }

    public function getStaleIfError()
    {
        return array_key_exists('stale-if-error', $this->attributes) ? $this->attributes['stale-if-error'] : null;
    }

    public function mustRevalidate()
    {
        $this->checkAttribute('must-revalidate', 'response');

        return array_key_exists('must-revalidate', $this->attributes);
    }

    public function setMustRevalidate($value)
    {
        $this->checkAttribute('must-revalidate', 'response');

        $this->setValue('must-revalidate', $value);
    }

    public function mustProxyRevalidate()
    {
        $this->checkAttribute('proxy-revalidate', 'response');

        return array_key_exists('proxy-revalidate', $this->attributes);
    }

    public function setProxyRevalidate($value)
    {
        $this->checkAttribute('proxy-revalidate', 'response');

        $this->setValue('proxy-revalidate', $value);
    }

    /**
     * Parses a Cache-Control HTTP header.
     *
     * @param string $header The value of the Cache-Control HTTP header
     *
     * @return array An array representing the attribute values
     */
    protected function parse($header)
    {
        $attributes = array();
        preg_match_all('#([a-zA-Z][a-zA-Z_-]*)\s*(?:=(?:"([^"]*)"|([^ \t",;]*)))?#', $header, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $attributes[strtolower($match[1])] = isset($match[2]) && $match[2] ? $match[2] : (isset($match[3]) ? $match[3] : true);
        }

        return $attributes;
    }

    protected function setValue($key, $value, $isBoolean = false)
    {
        if (false === $value) {
            unset($this->attributes[$key]);
        } else {
            $this->attributes[$key] = $isBoolean ? true : $value;
        }

        $this->bag->set('Cache-Control', (string) $this);
    }

    protected function checkAttribute($name, $expected)
    {
        if (null !== $this->type && $expected !== $this->type) {
            throw new \LogicException(sprintf("The property %s only applies to the %s Cache-Control.", $name, $expected));
        }
    }
}
