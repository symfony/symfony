<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Resource;

/**
 * ClassExistenceResource represents a class existence.
 * Freshness is only evaluated against resource existence.
 *
 * The resource must be a fully-qualified class name.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ClassExistenceResource implements SelfCheckingResourceInterface, \Serializable
{
    const EXISTS_OK = 1;
    const EXISTS_KO = 0;
    const EXISTS_KO_WITH_THROWING_AUTOLOADER = -1;

    private $resource;
    private $existsStatus;

    private static $checkingLevel = 0;
    private static $throwingAutoloader;
    private static $existsCache = array();

    /**
     * @param string   $resource     The fully-qualified class name
     * @param int|null $existsStatus One of the self::EXISTS_* const if the existency check has already been done
     */
    public function __construct($resource, $existsStatus = null)
    {
        $this->resource = $resource;
        if (null !== $existsStatus) {
            $this->existsStatus = (int) $existsStatus;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->resource;
    }

    /**
     * @return string The file path to the resource
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * {@inheritdoc}
     */
    public function isFresh($timestamp)
    {
        if (null !== $exists = &self::$existsCache[$this->resource]) {
            $exists = $exists || class_exists($this->resource, false) || interface_exists($this->resource, false) || trait_exists($this->resource, false);
        } elseif (self::EXISTS_KO_WITH_THROWING_AUTOLOADER === $this->existsStatus) {
            if (null === self::$throwingAutoloader) {
                $signalingException = new \ReflectionException();
                self::$throwingAutoloader = function () use ($signalingException) { throw $signalingException; };
            }
            if (!self::$checkingLevel++) {
                spl_autoload_register(self::$throwingAutoloader);
            }

            try {
                $exists = class_exists($this->resource) || interface_exists($this->resource, false) || trait_exists($this->resource, false);
            } catch (\ReflectionException $e) {
                $exists = false;
            } finally {
                if (!--self::$checkingLevel) {
                    spl_autoload_unregister(self::$throwingAutoloader);
                }
            }
        } else {
            $exists = class_exists($this->resource) || interface_exists($this->resource, false) || trait_exists($this->resource, false);
        }

        if (null === $this->existsStatus) {
            $this->existsStatus = $exists ? self::EXISTS_OK : self::EXISTS_KO;
        }

        return self::EXISTS_OK === $this->existsStatus xor !$exists;
    }

    /**
     * {@inheritdoc}
     */
    public function serialize()
    {
        if (null === $this->existsStatus) {
            $this->isFresh(0);
        }

        return serialize(array($this->resource, $this->existsStatus));
    }

    /**
     * {@inheritdoc}
     */
    public function unserialize($serialized)
    {
        list($this->resource, $this->existsStatus) = unserialize($serialized);
    }
}
