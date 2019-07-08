<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\AutowiringTypes;

use Doctrine\Common\Annotations\Reader;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class AutowiredServices
{
    private $annotationReader;
    private $dispatcher;
    private $cachePool;

    public function __construct(Reader $annotationReader = null, EventDispatcherInterface $dispatcher, CacheItemPoolInterface $cachePool)
    {
        $this->annotationReader = $annotationReader;
        $this->dispatcher = $dispatcher;
        $this->cachePool = $cachePool;
    }

    public function getAnnotationReader()
    {
        return $this->annotationReader;
    }

    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    public function getCachePool()
    {
        return $this->cachePool;
    }
}
