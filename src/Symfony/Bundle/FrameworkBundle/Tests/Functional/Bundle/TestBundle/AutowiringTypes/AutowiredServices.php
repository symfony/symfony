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

class AutowiredServices
{
    private $annotationReader;
    private $frameworkBundleEngine;
    private $engine;

    public function __construct(Reader $annotationReader = null, FrameworkBundleEngineInterface $frameworkBundleEngine, EngineInterface $engine)
    {
        $this->annotationReader = $annotationReader;
        $this->frameworkBundleEngine = $frameworkBundleEngine;
        $this->engine = $engine;
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
}
