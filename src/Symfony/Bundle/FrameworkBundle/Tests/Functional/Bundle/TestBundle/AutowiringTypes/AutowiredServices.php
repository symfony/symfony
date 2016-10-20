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
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;
use Symfony\Component\Templating\EngineInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AutowiredServices
{
    private $annotationReader;
    private $frameworkBundleEngine;
    private $engine;
    private $dispatcher;

    public function __construct(Reader $annotationReader = null, FrameworkBundleEngineInterface $frameworkBundleEngine, EngineInterface $engine, EventDispatcherInterface $dispatcher)
    {
        $this->annotationReader = $annotationReader;
        $this->frameworkBundleEngine = $frameworkBundleEngine;
        $this->engine = $engine;
        $this->dispatcher = $dispatcher;
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
}
