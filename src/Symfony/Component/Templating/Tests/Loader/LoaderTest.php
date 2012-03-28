<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Loader;

use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\TemplateReferenceInterface;

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSetDebugger()
    {
        $loader = new ProjectTemplateLoader4(new TemplateNameParser());
        $loader->setDebugger($debugger = new \Symfony\Component\Templating\Tests\Fixtures\ProjectTemplateDebugger());
        $this->assertTrue($loader->getDebugger() === $debugger, '->setDebugger() sets the debugger instance');
    }
}

class ProjectTemplateLoader4 extends Loader
{
    public function load(TemplateReferenceInterface $template)
    {
    }

    public function getDebugger()
    {
        return $this->debugger;
    }

    public function isFresh(TemplateReferenceInterface $template, $time)
    {
        return false;
    }
}
