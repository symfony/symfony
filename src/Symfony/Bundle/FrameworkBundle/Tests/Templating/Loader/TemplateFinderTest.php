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

use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateNameParser;
use Symfony\Bundle\FrameworkBundle\Templating\Loader\TemplateFinder;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\BaseBundle\BaseBundle;


class TemplateFinderTest extends TestCase
{
    public function testFindAllTemplates()
    {       
        $kernel = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Kernel')
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

        $parser = new TemplateNameParser($kernel);

        $finder = new TemplateFinder($kernel, $parser, __DIR__.'/../../Fixtures/Resources');

        $templates = array_map(
            function ($template) { return $template->getLogicalName(); },
            $finder->findAllTemplates()
        );

        $this->assertEquals(3, count($templates), '->findAllTemplates() find all templates in the bundles and global folders');
        $this->assertContains('BaseBundle::base.format.engine', $templates);
        $this->assertContains('BaseBundle:controller:base.format.engine', $templates);
        $this->assertContains('::resource.format.engine', $templates);
    }

}
