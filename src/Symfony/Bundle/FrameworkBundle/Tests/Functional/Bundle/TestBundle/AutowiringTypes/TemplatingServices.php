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

use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface as FrameworkBundleEngineInterface;
use Symfony\Component\Templating\EngineInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class TemplatingServices
{
    private $frameworkBundleEngine;
    private $engine;

    public function __construct(FrameworkBundleEngineInterface $frameworkBundleEngine, EngineInterface $engine)
    {
        $this->frameworkBundleEngine = $frameworkBundleEngine;
        $this->engine = $engine;
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
