<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Templating\Loader;

require_once __DIR__.'/../Fixtures/ProjectTemplateDebugger.php';

use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\Loader\TemplateNameParser;

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSetDebugger()
    {
        $loader = new ProjectTemplateLoader4(new TemplateNameParser());
        $loader->setDebugger($debugger = new \ProjectTemplateDebugger());
        $this->assertTrue($loader->getDebugger() === $debugger, '->setDebugger() sets the debugger instance');
    }
}

class ProjectTemplateLoader4 extends Loader
{
    public function load($template)
    {
    }

    public function getDebugger()
    {
        return $this->debugger;
    }

    public function isFresh($template, $time)
    {
        return false;
    }
}
