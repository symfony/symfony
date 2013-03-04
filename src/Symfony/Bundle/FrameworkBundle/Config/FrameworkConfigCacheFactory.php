<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Config;

use Symfony\Component\Config\AbstractConfigCacheFactory;
use Symfony\Component\Config\ResourceValidatingCache;
use Symfony\Component\Config\NonvalidatingCache;
use Symfony\Component\Config\Resource\ResourceValidatorInterface;

/**
 * This implementation of ConfigCacheFactoryInterface will use a given
 * set of ResourceValidators to check caches for freshness. If no
 * ResourceValidators are used, a Non-validating cache will be used.
 *
 * @author Matthias Pigulla <mp@webfactory.de>
 */
class FrameworkConfigCacheFactory extends AbstractConfigCacheFactory
{
    protected $resourceValidators = array();

    public function addResourceValidator(ResourceValidatorInterface $validator)
    {
        $this->resourceValidators[] = $validator;
    }

    public function setResourceValidators(array $validators)
    {
        $this->resourceValidators = $validators;
    }

    public function createCache($cacheFilename)
    {
        if ($this->resourceValidators) {
            $cache = new ResourceValidatingCache($cacheFilename);
            $cache->setResourceValidators($this->resourceValidators);
        } else {
            $cache = new NonvalidatingCache($cacheFilename);
        }
        return $cache;
    }
}
