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
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Templating\EngineInterface;

class AutowiredServices
{
    private $annotationReader;
    private $frameworkBundleEngine;
    private $engine;
    private $dispatcher;
    private $cachePool;

    public function __construct(Reader $annotationReader = null, FrameworkBundleEngineInterface $frameworkBundleEngine, EngineInterface $engine, EventDispatcherInterface $dispatcher, CacheItemPoolInterface $cachePool)
    {
        $this->annotationReader = $annotationReader;
        $this->frameworkBundleEngine = $frameworkBundleEngine;
        $this->engine = $engine;
        $this->dispatcher = $dispatcher;
        $this->cachePool = $cachePool;
    }

    public function getAnnotationReader()
    {
        return $this->annotationReader;
    }

    public function getFrameworkBundleEngine()
    {
        return $this->frameworkBundleEngine;
    }

    public function getEngine()
    {
        return $this->engine;
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
