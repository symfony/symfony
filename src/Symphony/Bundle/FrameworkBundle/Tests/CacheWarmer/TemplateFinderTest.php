<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\CacheWarmer;

use Symphony\Bundle\FrameworkBundle\Tests\TestCase;
use Symphony\Bundle\FrameworkBundle\Templating\TemplateFilenameParser;
use Symphony\Bundle\FrameworkBundle\CacheWarmer\TemplateFinder;
use Symphony\Bundle\FrameworkBundle\Tests\Fixtures\BaseBundle\BaseBundle;

class TemplateFinderTest extends TestCase
{
    public function testFindAllTemplates()
    {
        $kernel = $this
            ->getMockBuilder('Symphony\Component\HttpKernel\Kernel')
            ->disableOriginalConstructor()
            ->getMock()
        ;

        $kernel
            ->expects($this->any())
            ->method('getBundle')
        ;

        $kernel
            ->expects($this->once())
            ->method('getBundles')
            ->will($this->returnValue(array('BaseBundle' => new BaseBundle())))
        ;

        $parser = new TemplateFilenameParser();

        $finder = new TemplateFinder($kernel, $parser, __DIR__.'/../Fixtures/Resources');

        $templates = array_map(
            function ($template) { return $template->getLogicalName(); },
            $finder->findAllTemplates()
        );

        $this->assertCount(7, $templates, '->findAllTemplates() find all templates in the bundles and global folders');
        $this->assertContains('BaseBundle::base.format.engine', $templates);
        $this->assertContains('BaseBundle::this.is.a.template.format.engine', $templates);
        $this->assertContains('BaseBundle:controller:base.format.engine', $templates);
        $this->assertContains('BaseBundle:controller:custom.format.engine', $templates);
        $this->assertContains('::this.is.a.template.format.engine', $templates);
        $this->assertContains('::resource.format.engine', $templates);
    }
}
