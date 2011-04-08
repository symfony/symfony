<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Templating\Loader;

use Symfony\Bundle\FrameworkBundle\Templating\Loader\CachedTemplateLocator;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateLocator;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;

class CachedTemplateLocatorTest extends TestCase
{
    public function testLocateACachedTemplate()
    {
        $template = new TemplateReference('bundle', 'controller', 'name', 'format', 'engine');

        $locator = $this
            ->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\Loader\CachedTemplateLocator')
            ->setMethods(array('getCachedTemplatePath'))
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $locator
            ->expects($this->once())
            ->method('getCachedTemplatePath')
            ->with($template)
            ->will($this->returnValue('/cached/path/to/template'))
        ;

        $this->assertEquals('/cached/path/to/template', $locator->locate($template));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testThrowsAnExceptionWhenTemplateIsNotATemplateReferenceInterface()
    {
        $locator = $this
            ->getMockBuilder('Symfony\Bundle\FrameworkBundle\Templating\Loader\CachedTemplateLocator')
            ->setMethods(array('getCacheTemplatePath'))
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $locator->locate('template');
    }
}
