<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config;

/**
 * A ConfigCacheFactory implementation that validates the
 * cache with an arbitrary set of metadata validators.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class ValidatorConfigCacheFactory implements ConfigCacheFactoryInterface
{
    /**
     * @var bool
     */
    private $debug;

    /**
     * @var MetadataValidatorInterface[]
     */
    private $validators = array();

    /**
     * @param bool $debug The debug flag to pass to ConfigCache
     */
    public function __construct($debug)
    {
        $this->debug = $debug;
    }

    /**
     * @param MetadataValidatorInterface $validator
     */
    public function addValidator(MetadataValidatorInterface $validator)
    {
        $this->validators[] = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public function cache($file, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException(sprintf('Invalid type for callback argument. Expected callable, but got "%s".', gettype($callback)));
        }

        $cache = new ConfigCache($file, $this->debug);
        if (!$cache->isFresh($this->validators)) {
            call_user_func($callback, $cache);
        }

        return $cache;
    }
}
