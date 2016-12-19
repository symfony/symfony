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
use Symfony\Component\Templating\TemplateReferenceInterface;

class LoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetSetLogger()
    {
        $loader = new ProjectTemplateLoader4();
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $loader->setLogger($logger);
        $this->assertSame($logger, $loader->getLogger(), '->setLogger() sets the logger instance');
    }

    /**
     * @group legacy
     */
    public function testLegacyGetSetDebugger()
    {
        $loader = new ProjectTemplateLoader4();
        $debugger = $this->getMockBuilder('Symfony\Component\Templating\DebuggerInterface')->getMock();
        $loader->setDebugger($debugger);
        $this->assertSame($debugger, $loader->getDebugger(), '->setDebugger() sets the debugger instance');
    }
}

class ProjectTemplateLoader4 extends Loader
{
    public function load(TemplateReferenceInterface $template)
    {
    }

    public function getLogger()
    {
        return $this->logger;
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
