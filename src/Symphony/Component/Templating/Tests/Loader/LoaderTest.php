<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Templating\Tests\Loader;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Templating\Loader\Loader;
use Symphony\Component\Templating\TemplateReferenceInterface;

class LoaderTest extends TestCase
{
    public function testGetSetLogger()
    {
        $loader = new ProjectTemplateLoader4();
        $logger = $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock();
        $loader->setLogger($logger);
        $this->assertSame($logger, $loader->getLogger(), '->setLogger() sets the logger instance');
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
