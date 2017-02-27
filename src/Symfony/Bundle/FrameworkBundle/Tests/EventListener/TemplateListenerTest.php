<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\EventListener;

use Symfony\Bundle\FrameworkBundle\EventListener\TemplateListener;
use Symfony\Bundle\FrameworkBundle\Templating\PhpEngine;
use Symfony\Bundle\FrameworkBundle\Templating\TemplatedResponse;
use Symfony\Bundle\FrameworkBundle\Templating\TemplateReference;
use Symfony\Bundle\FrameworkBundle\Templating\TemplatedResponseInterface;
use Symfony\Bundle\FrameworkBundle\Tests\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Templating\Loader\Loader;
use Symfony\Component\Templating\Storage\StringStorage;
use Symfony\Component\Templating\TemplateNameParser;
use Symfony\Component\Templating\TemplateReferenceInterface;

class TemplateListenerTest extends TestCase
{
    public function testTemplateReference()
    {
        $template = new TemplatedResponse('dummy_template.html.php', array('var' => 'dummy'));

        $event = $this->getEvent($template);

        $listener = new TemplateListener($this->getPhpEngine());
        $listener->onView($event);

        $response = $event->getResponse();

        $this->assertSame('This is dummy content', $response->getContent());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testInvalidResponse()
    {
        $templating = $this->getPhpEngine();

        $template = $this->getMockBuilder(TemplatedResponseInterface::class)->getMock();
        $template->expects($this->once())
            ->method('getResponse')
            ->with($templating)
            ->will($this->throwException(new \LogicException()));

        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('LogicException');

        $event = $this->getEvent($template);

        $listener = new TemplateListener($templating);
        $listener->onView($event);
    }

    private function getEvent($template)
    {
        $request = new Request(array(), array(), array());
        $mockKernel = $this->getMockForAbstractClass('Symfony\Component\HttpKernel\Kernel', array('', ''));

        return new GetResponseForControllerResultEvent($mockKernel, $request, Kernel::MASTER_REQUEST, $template);
    }

    private function getPhpEngine()
    {
        $container = new Container();
        $loader = new ProjectTemplateLoader();

        $loader->templates['dummy_template.html.php'] = 'This is <?= $var ?> content';

        $engine = new PhpEngine(new TemplateNameParser(), $container, $loader);

        return $engine;
    }
}

class ProjectTemplateLoader extends Loader
{
    public $templates = array();

    public function setTemplate($name, $content)
    {
        $template = new TemplateReference($name, 'php');
        $this->templates[$template->getLogicalName()] = $content;
    }

    public function load(TemplateReferenceInterface $template)
    {
        if (isset($this->templates[$template->getLogicalName()])) {
            return new StringStorage($this->templates[$template->getLogicalName()]);
        }

        return false;
    }

    public function isFresh(TemplateReferenceInterface $template, $time)
    {
        return false;
    }
}
