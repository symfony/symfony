<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bridge\Twig\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Bridge\Twig\TwigEngine;
use Symphony\Component\Templating\TemplateReference;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigEngineTest extends TestCase
{
    public function testExistsWithTemplateInstances()
    {
        $engine = $this->getTwig();

        $this->assertTrue($engine->exists($this->getMockForAbstractClass('Twig\Template', array(), '', false)));
    }

    public function testExistsWithNonExistentTemplates()
    {
        $engine = $this->getTwig();

        $this->assertFalse($engine->exists('foobar'));
        $this->assertFalse($engine->exists(new TemplateReference('foorbar')));
    }

    public function testExistsWithTemplateWithSyntaxErrors()
    {
        $engine = $this->getTwig();

        $this->assertTrue($engine->exists('error'));
        $this->assertTrue($engine->exists(new TemplateReference('error')));
    }

    public function testExists()
    {
        $engine = $this->getTwig();

        $this->assertTrue($engine->exists('index'));
        $this->assertTrue($engine->exists(new TemplateReference('index')));
    }

    public function testRender()
    {
        $engine = $this->getTwig();

        $this->assertSame('foo', $engine->render('index'));
        $this->assertSame('foo', $engine->render(new TemplateReference('index')));
    }

    /**
     * @expectedException \Twig\Error\SyntaxError
     */
    public function testRenderWithError()
    {
        $engine = $this->getTwig();

        $engine->render(new TemplateReference('error'));
    }

    protected function getTwig()
    {
        $twig = new Environment(new ArrayLoader(array(
            'index' => 'foo',
            'error' => '{{ foo }',
        )));
        $parser = $this->getMockBuilder('Symphony\Component\Templating\TemplateNameParserInterface')->getMock();

        return new TwigEngine($twig, $parser);
    }
}
